<?php
namespace WPNexusAI\Queue;

use WPNexusAI\Logging\Logger;
use WPNexusAI\DB\Repos\JobsRepo;
use WPNexusAI\Queue\Tasks\TaskRegistry;
use WPNexusAI\Queue\Tasks\TaskResult;
use WPNexusAI\Queue\Dispatcher;

if (!defined('ABSPATH')) {
	exit;
}

final class JobRunner {

	public static function register(): void {
		add_action('wpnexus_ai_run_job', [__CLASS__, 'handle'], 10, 1);
		add_action('wpnexus_ai_jobs_sweep', [__CLASS__, 'sweep'], 10, 0);

		// Safety net sweep: use WP-Cron only to avoid Action Scheduler "too early" notices.
		add_action('init', [__CLASS__, 'ensure_sweep_scheduled_wp_cron'], 20);

		Logger::instance()->info('queue.runner.registered', [
			'hooks' => ['wpnexus_ai_run_job', 'wpnexus_ai_jobs_sweep'],
			'action_scheduler' => function_exists('as_schedule_single_action'),
		]);
	}

	public static function ensure_sweep_scheduled_wp_cron(): void {
		add_filter('cron_schedules', function ($schedules) {
			if (!isset($schedules['wpnexus_ai_5min'])) {
				$schedules['wpnexus_ai_5min'] = [
					'interval' => 300,
					'display'  => 'WPNexus AI (5 minutes)',
				];
			}
			return $schedules;
		});

		$current = function_exists('wp_get_schedule') ? wp_get_schedule('wpnexus_ai_jobs_sweep') : false;

		// If not scheduled, schedule 5-minute sweep.
		if (!$current) {
			wp_schedule_event(time() + 60, 'wpnexus_ai_5min', 'wpnexus_ai_jobs_sweep');
			Logger::instance()->info('queue.sweep.scheduled.wp_cron', ['interval' => 300]);
			return;
		}

		// If scheduled with a different interval (e.g. hourly), upgrade to 5-minute sweep.
		if ($current !== 'wpnexus_ai_5min') {
			wp_clear_scheduled_hook('wpnexus_ai_jobs_sweep');
			wp_schedule_event(time() + 60, 'wpnexus_ai_5min', 'wpnexus_ai_jobs_sweep');

			Logger::instance()->info('queue.sweep.rescheduled.wp_cron', [
				'from'     => (string) $current,
				'interval' => 300,
			]);
		}
	}

	public static function handle($job_id): void {
		$job_id = (int) $job_id;
		$logger = Logger::instance();

		$logger->info('queue.run.start', ['job_id' => $job_id]);

		if ($job_id <= 0) {
			$logger->warning('queue.run.invalid_job_id');
			return;
		}

		$repo = new JobsRepo();
		$job  = $repo->get($job_id);

		if (!$job) {
			$logger->warning('queue.run.job_missing', ['job_id' => $job_id]);
			return;
		}

		$status = (string) ($job['status'] ?? '');
		if (in_array($status, ['done', 'failed', 'needs_input'], true)) {
			$logger->debug('queue.run.skip.already_terminal', ['job_id' => $job_id, 'status' => $status]);
			return;
		}

		// If scheduled too early, reschedule at next_run_at.
		$next_run_at = isset($job['next_run_at']) ? (string) $job['next_run_at'] : '';
		if ($next_run_at !== '') {
			$due_ts = strtotime($next_run_at . ' UTC');
			if (is_int($due_ts) && $due_ts > (time() + 2)) {
				$logger->info('queue.run.not_due.reschedule', [
					'job_id'      => $job_id,
					'next_run_at' => $next_run_at,
				]);
				(new Dispatcher())->schedule($job_id, $due_ts);
				return;
			}
		}

		// Mark running (atomic-ish guard: only queued -> running)
		if (!$repo->mark_running($job_id)) {
			$logger->debug('queue.run.locked_or_not_queued', ['job_id' => $job_id]);
			return;
		}

		// Reload after mark_running
		$job = $repo->get($job_id);
		if (!$job) {
			$logger->warning('queue.run.job_missing_after_mark', ['job_id' => $job_id]);
			return;
		}

		$type     = (string) ($job['type'] ?? '');
		$registry = new TaskRegistry();
		$task     = $registry->resolve($type);

		$logger->info('queue.task.start', ['job_id' => $job_id, 'type' => $type]);

		try {
			$result = $task->handle($job);
		} catch (\Throwable $e) {
			$logger->error('queue.task.exception', [
				'job_id' => $job_id,
				'type'   => $type,
				'msg'    => $e->getMessage(),
			]);
			$repo->mark_failed($job_id, 'Exception: ' . $e->getMessage(), 'failed');
			return;
		}

		if (!($result instanceof TaskResult)) {
			$repo->mark_failed($job_id, 'Task did not return TaskResult.', 'failed');
			return;
		}

		if ($result->status === TaskResult::DONE) {
			$repo->mark_done($job_id);
			$logger->info('queue.task.done', ['job_id' => $job_id, 'type' => $type]);
			return;
		}

		if ($result->status === TaskResult::RETRY && is_int($result->next_run_ts) && $result->next_run_ts > 0) {
			$repo->reschedule($job_id, $result->next_run_ts, (string) ($result->error ?? ''));
			(new Dispatcher())->schedule($job_id, $result->next_run_ts);
			$logger->warning('queue.task.retry', [
				'job_id' => $job_id,
				'type'   => $type,
				'next'   => $result->next_run_ts,
			]);
			return;
		}

		if ($result->status === TaskResult::NEEDS_INPUT) {
			$repo->mark_failed($job_id, (string) ($result->error ?? 'Needs input.'), 'needs_input');
			$logger->warning('queue.task.needs_input', ['job_id' => $job_id, 'type' => $type]);
			return;
		}

		$repo->mark_failed($job_id, (string) ($result->error ?? 'Failed.'), 'failed');
		$logger->warning('queue.task.failed', ['job_id' => $job_id, 'type' => $type]);
	}

	public static function sweep(): void {
		$logger = Logger::instance();
		$repo   = new JobsRepo();
		$due    = $repo->due(20);

		$logger->info('queue.sweep.start', ['count' => count($due)]);

		$dispatcher = new Dispatcher();

		foreach ($due as $job) {
			$job_id = isset($job['id']) ? (int) $job['id'] : 0;
			if ($job_id > 0) {
				$dispatcher->schedule($job_id, time() + 5);
			}
		}

		$logger->info('queue.sweep.done', ['count' => count($due)]);
	}
}

