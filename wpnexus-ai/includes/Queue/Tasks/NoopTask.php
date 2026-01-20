<?php
namespace WPNexusAI\Queue\Tasks;

if (!defined('ABSPATH')) {
	exit;
}

final class NoopTask implements TaskInterface {

	/** @var string */
	private $type;

	public function __construct(string $type) {
		$this->type = sanitize_key($type);
	}

	public function type(): string {
		return $this->type;
	}

	public function handle(array $job_row): TaskResult {
		return TaskResult::failed('Task type not implemented yet: ' . $this->type);
	}
}
