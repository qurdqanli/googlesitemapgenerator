<?php
namespace WPNexusAI\Core;

use WPNexusAI\Logging\Logger;

if (!defined('ABSPATH')) {
	exit;
}

final class Deactivator {

	public static function deactivate(): void {
		$logger = Logger::instance();
		$logger->info('core.deactivate.start');

		// Clear WP-Cron sweep hook.
		wp_clear_scheduled_hook('wpnexus_ai_jobs_sweep');

		// If Action Scheduler exists, unschedule our group (best-effort).
		if (function_exists('as_unschedule_all_actions')) {
			@as_unschedule_all_actions('wpnexus_ai_run_job', [], 'wpnexus-ai');
		}

		$logger->info('core.deactivate.done');
	}
}

