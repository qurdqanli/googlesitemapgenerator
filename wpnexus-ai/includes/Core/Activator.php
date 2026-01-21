<?php
namespace WPNexusAI\Core;

use WPNexusAI\Logging\Logger;
use WPNexusAI\DB\Installer;

if (!defined('ABSPATH')) {
	exit;
}

final class Activator {

	public static function activate(): void {
		$logger = Logger::instance();
		$logger->info('core.activate.start', [
			'version' => defined('WPNEXUS_AI_VERSION') ? WPNEXUS_AI_VERSION : null,
		]);

		add_option('wpnexus_ai_installed_at', time(), '', false);

		Installer::install();

		// WP-Cron sweep (fallback). Action Scheduler varsa da problem deyil: sadəcə "safety-net".
		if (!wp_next_scheduled('wpnexus_ai_jobs_sweep')) {
			wp_schedule_event(time() + 60, 'hourly', 'wpnexus_ai_jobs_sweep');
			$logger->info('queue.sweep.scheduled', ['hook' => 'wpnexus_ai_jobs_sweep']);
		}

		$logger->info('core.activate.done');
	}
}

