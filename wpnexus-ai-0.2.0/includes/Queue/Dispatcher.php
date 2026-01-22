<?php
namespace WPNexusAI\Queue;

use WPNexusAI\Logging\Logger;

if (!defined('ABSPATH')) { exit; }

final class Dispatcher {

    /** @var JobsRepo */
    private $repo;

    /** @var Logger */
    private $logger;

    public function __construct() {
        $this->repo = new JobsRepo();
        $this->logger = Logger::instance();
    }

    /**
     * Enqueue a job in our DB and (if available) schedule an Action Scheduler hook for it.
     */
    public function enqueue(string $type, array $payload, int $delay_seconds = 0): int {
        $ts = time() + max(0, $delay_seconds);
        $scheduled_at = $delay_seconds > 0 ? gmdate('Y-m-d H:i:s', $ts) : null;

        // First create our DB job.
        $job_id = $this->repo->insert($type, $payload, null, $scheduled_at);

        // Then schedule Action Scheduler (if present) with real job_id.
        if (function_exists('as_schedule_single_action')) {
            $as_action_id = as_schedule_single_action($ts, JobRunner::HOOK_RUN_JOB, [ 'job_id' => $job_id ], JobRunner::GROUP);
            if ($as_action_id) {
                global $wpdb;
                $table = $wpdb->prefix . 'wpnexus_ai_jobs';
                $wpdb->update($table, ['as_action_id' => $as_action_id], ['id' => $job_id]);
            }
        }

        $this->logger->info('queue.job.enqueued', ['job_id' => $job_id, 'type' => $type, 'delay' => $delay_seconds]);
        return $job_id;
    }
}
