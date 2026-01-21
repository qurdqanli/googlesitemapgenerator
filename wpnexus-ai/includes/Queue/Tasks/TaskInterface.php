<?php
namespace WPNexusAI\Queue\Tasks;

if (!defined('ABSPATH')) {
	exit;
}

interface TaskInterface {

	public function type(): string;

	/**
	 * @param array<string,mixed> $job_row
	 */
	public function handle(array $job_row): TaskResult;
}
