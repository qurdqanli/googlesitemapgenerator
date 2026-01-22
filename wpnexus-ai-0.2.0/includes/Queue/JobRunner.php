<?php
namespace WPNexusAI\Queue;

use WPNexusAI\Logging\Logger;
use WPNexusAI\Services\UpsertService;

if (!defined('ABSPATH')) { exit; }

final class JobRunner {

    public const GROUP = 'wpnexus_ai';
    public const HOOK_RUN_JOB = 'wpnexus_ai_run_job';
    public const HOOK_SWEEP = 'wpnexus_ai_jobs_sweep';

    public static function register(): void {
        add_action(self::HOOK_RUN_JOB, [__CLASS__, 'run_job_action'], 10, 1);
        add_action(self::HOOK_SWEEP, [__CLASS__, 'sweep_action']);

        // WP-Cron fallback: register schedule.
        add_filter('cron_schedules', function ($schedules) {
            if (!isset($schedules['wpnexus_5min'])) {
                $schedules['wpnexus_5min'] = [
                    'interval' => 300,
                    'display' => 'Every 5 minutes (WPNexus)',
                ];
            }
            return $schedules;
        });

        // Ensure sweep exists after plugins_loaded.
        add_action('init', function () {
            self::ensure_sweep();
        }, 20);
    }

    public static function ensure_sweep(): void {
        // Prefer Action Scheduler recurring sweep.
        if (function_exists('as_schedule_recurring_action') && function_exists('as_next_scheduled_action')) {
            $next = as_next_scheduled_action(self::HOOK_SWEEP, [], self::GROUP);
            if (!$next) {
                as_schedule_recurring_action(time() + 60, 300, self::HOOK_SWEEP, [], self::GROUP);
                Logger::instance()->info('queue.sweep.scheduled', ['engine' => 'action_scheduler']);
            }
            return;
        }

        // Fallback to WP-Cron.
        if (!wp_next_scheduled(self::HOOK_SWEEP)) {
            wp_schedule_event(time() + 60, 'wpnexus_5min', self::HOOK_SWEEP);
            Logger::instance()->info('queue.sweep.scheduled', ['engine' => 'wp_cron']);
        }
    }

    public static function disable_sweep(): void {
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(self::HOOK_SWEEP, [], self::GROUP);
            as_unschedule_all_actions(self::HOOK_RUN_JOB, [], self::GROUP);
        }
        $ts = wp_next_scheduled(self::HOOK_SWEEP);
        if ($ts) {
            wp_unschedule_event($ts, self::HOOK_SWEEP);
        }
    }

    public static function sweep_action(): void {
        $logger = Logger::instance();
        $repo = new JobsRepo();
        $ids = $repo->due_pending_ids(10);

        if (!$ids) {
            $logger->debug('queue.sweep.none');
            return;
        }

        foreach ($ids as $id) {
            // If Action Scheduler exists, schedule per-job action (better concurrency).
            if (function_exists('as_schedule_single_action')) {
                as_schedule_single_action(time() + 1, self::HOOK_RUN_JOB, ['job_id' => $id], self::GROUP);
            } else {
                // Fallback: run inline (still cheap, job execution has its own timeouts).
                self::run_job_action(['job_id' => $id]);
            }
        }

        $logger->info('queue.sweep.scheduled_jobs', ['count' => count($ids)]);
    }

    /**
     * @param array|string|int $args Either args array or job_id for older hooks.
     */
    public static function run_job_action($args): void {
        $job_id = 0;
        if (is_array($args) && isset($args['job_id'])) {
            $job_id = (int) $args['job_id'];
        } elseif (is_numeric($args)) {
            $job_id = (int) $args;
        }

        $logger = Logger::instance();
        if ($job_id <= 0) {
            $logger->warn('queue.run_job.invalid_args', ['args' => $args]);
            return;
        }

        $repo = new JobsRepo();
        $row = $repo->get($job_id);
        if (!$row) {
            $logger->warn('queue.run_job.not_found', ['job_id' => $job_id]);
            return;
        }

        if ((string) $row['status'] !== 'pending') {
            $logger->debug('queue.run_job.skip_not_pending', ['job_id' => $job_id, 'status' => (string) $row['status']]);
            return;
        }

        $repo->set_status($job_id, 'running');
        $repo->inc_attempts($job_id);

        $payload = $repo->payload($row);

        try {
            if ((string) $row['type'] === 'upsert') {
                $svc = new UpsertService();
                $svc->handle_job($job_id, $payload);
                $repo->set_status($job_id, 'done');
            } else {
                $repo->set_status($job_id, 'failed', 'Unknown job type: ' . (string) $row['type']);
            }
        } catch (\Throwable $e) {
            $repo->set_status($job_id, 'failed', $e->getMessage());
            $logger->error('queue.run_job.exception', ['job_id' => $job_id, 'err' => $e->getMessage()]);
        }
    }
}
