<?php
namespace WPNexusAI\Queue;

use WPNexusAI\Logging\Logger;
use WPNexusAI\DB\Repos\JobsRepo;

if (!defined('ABSPATH')) {
	exit;
}

final class Dispatcher {

	/** @var Logger */
	private $logger;

	/** @var JobsRepo */
	private $jobs;

	public function __construct() {
		$this->logger = Logger::instance();
		$this->jobs   = new JobsRepo();
	}

	/**
	 * Enqueue a job and schedule execution.
	 *
	 * @param array<string,mixed> $payload
	 */
	public function enqueue(string $type, array $payload, ?int $run_at_ts = null): int {
		$this->logger->info('queue.enqueue.start', [
			'type' => $type,
			'run_at_ts' => $run_at_ts,
		]);

		$job_id = $this->jobs->create($type, $payload, $run_at_ts);

		if ($job_id > 0) {
			$this->schedule($job_id, $run_at_ts);
		}

		$this->logger->info('queue.enqueue.done', [
			'job_id' => $job_id,
			'type'   => $type,
		]);

		return $job_id;
	}

	public function schedule(int $job_id, ?int $run_at_ts = null): void {
		$job_id = (int) $job_id;
		if ($job_id <= 0) {
			return;
		}

		$ts = is_int($run_at_ts) && $run_at_ts > 0 ? $run_at_ts : (time() + 5);
		$args = [$job_id];

		// Action Scheduler primary
		if (function_exists('as_schedule_single_action')) {
			// group helps filtering/cleanup
			as_schedule_single_action($ts, 'wpnexus_ai_run_job', $args, 'wpnexus-ai');
			$this->logger->info('queue.schedule.action_scheduler', [
				'job_id' => $job_id,
				'ts'     => $ts,
			]);
			return;
		}

		// WP-Cron fallback
		if (!wp_next_scheduled('wpnexus_ai_run_job', $args)) {
			wp_schedule_single_event($ts, 'wpnexus_ai_run_job', $args);
		}

		$this->logger->info('queue.schedule.wp_cron', [
			'job_id' => $job_id,
			'ts'     => $ts,
		]);
	}
}
