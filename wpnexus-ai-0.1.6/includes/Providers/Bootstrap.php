<?php
namespace WPNexusAI\Providers;

use WPNexusAI\Queue\Tasks\TranslateTask;

if (!defined('ABSPATH')) {
	exit;
}

final class Bootstrap {

	public static function init(): void {
		add_filter('wpnexus_ai_queue_tasks', function ($tasks) {
			if (!is_array($tasks)) {
				$tasks = [];
			}
			$exists = false;
			foreach ($tasks as $t) {
				if ($t instanceof TranslateTask) { $exists = true; break; }
			}
			if (!$exists) {
				$tasks[] = new TranslateTask();
			}
			return $tasks;
		}, 10, 1);
	}
}

