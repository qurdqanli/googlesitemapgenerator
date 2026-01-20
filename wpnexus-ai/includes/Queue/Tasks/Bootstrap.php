<?php
namespace WPNexusAI\Queue\Tasks;

if (!defined('ABSPATH')) {
	exit;
}

final class Bootstrap {

	public static function init(): void {
		add_filter('wpnexus_ai_queue_tasks', function ($tasks) {
			if (!is_array($tasks)) {
				$tasks = [];
			}

			// TranslateTask varsa əlavə et (səndə əvvəlki mərhələdən ola bilər)
			$translate_class = '\\WPNexusAI\\Queue\\Tasks\\TranslateTask';
			if (class_exists($translate_class)) {
				$tasks[] = new $translate_class();
			}

			// UpsertTask (T11)
			$tasks[] = new UpsertTask();
 		
  	        // DeleteTask (T12)
			$tasks[] = new DeleteTask();
 		
  	        // ReconcileTask (T16)
			$tasks[] = new ReconcileTask();

			return $tasks;
		}, 10, 1);
	}
}
