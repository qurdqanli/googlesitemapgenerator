<?php
namespace WPNexusAI\Queue\Tasks;

if (!defined('ABSPATH')) {
	exit;
}

final class TaskRegistry {

	/** @var array<string,TaskInterface> */
	private $tasks = [];

	public function __construct() {
		/**
		 * Default/core tasks should always be available as a safe fallback.
		 * Filter-registered tasks can override these by returning the same type().
		 */
		$this->register_core_defaults();

		/**
		 * Allow other modules to register/override tasks.
		 *
		 * @param TaskInterface[] $tasks
		 */
		$provided = apply_filters('wpnexus_ai_queue_tasks', []);
		if (is_array($provided)) {
			foreach ($provided as $task) {
				if ($task instanceof TaskInterface) {
					$type = sanitize_key($task->type());
					if ($type !== '') {
						$this->tasks[$type] = $task; // override allowed
					}
				}
			}
		}
	}

	public function resolve(string $type): TaskInterface {
		$type = sanitize_key($type);
		return $this->tasks[$type] ?? new NoopTask($type);
	}

	private function register_core_defaults(): void {
		$candidates = [
			ReconcileTask::class,
			TranslateTask::class,
			UpsertTask::class,
			DeleteTask::class,
		];

		foreach ($candidates as $class) {
			if (!class_exists($class)) {
				continue;
			}

			try {
				$task = new $class();
			} catch (\Throwable $e) { // safety: do not break registry construction
				continue;
			}

			if ($task instanceof TaskInterface) {
				$type = sanitize_key($task->type());
				if ($type !== '') {
					$this->tasks[$type] = $task;
				}
			}
		}
	}
}

