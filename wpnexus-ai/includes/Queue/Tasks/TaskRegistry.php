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
		 * Allow other modules (future T11/T12/T13 etc) to register tasks.
		 *
		 * @param TaskInterface[] $tasks
		 */
		$provided = apply_filters('wpnexus_ai_queue_tasks', []);
		if (is_array($provided)) {
			foreach ($provided as $task) {
				if ($task instanceof TaskInterface) {
					$this->tasks[$task->type()] = $task;
				}
			}
		}
	}

	public function resolve(string $type): TaskInterface {
		$type = sanitize_key($type);
		return $this->tasks[$type] ?? new NoopTask($type);
	}
}
