<?php
namespace WPNexusAI\Logging;

use WPNexusAI\Util\Paths;

if (!defined('ABSPATH')) {
	exit;
}

final class Logger {

	/** @var Logger|null */
	private static $instance = null;

	/** @var string */
	private $request_id;

	private function __construct() {
		$this->request_id = $this->make_request_id();
	}

	public static function instance(): Logger {
		if (self::$instance === null) {
			self::$instance = new Logger();
		}
		return self::$instance;
	}

	public function debug(string $event, array $context = []): void {
		$this->log('debug', $event, $context);
	}

	public function info(string $event, array $context = []): void {
		$this->log('info', $event, $context);
	}

	public function warning(string $event, array $context = []): void {
		$this->log('warning', $event, $context);
	}

	public function error(string $event, array $context = []): void {
		$this->log('error', $event, $context);
	}

	private function log(string $level, string $event, array $context = []): void {
		$record = [
			'ts'         => gmdate('c'),
			'level'      => $level,
			'event'      => $event,
			'request_id' => $this->request_id,
			'context'    => $context,
		];

		$line = wp_json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		// Always to WP debug log as a baseline.
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('[WPNexusAI] ' . $line);
		}

		// Also to plugin log file (uploads/wpnexus-ai/logs).
		$this->write_file($line . "\n");
	}

	private function write_file(string $content): void {
		$dir = Paths::logs_dir();

		if (!is_dir($dir)) {
			wp_mkdir_p($dir);
		}

		$file = $dir . '/wpnexus-ai-' . gmdate('Y-m-d') . '.log';

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
		@file_put_contents($file, $content, FILE_APPEND | LOCK_EX);
	}

	private function make_request_id(): string {
		// Use WP's UUID if available.
		if (function_exists('wp_generate_uuid4')) {
			return wp_generate_uuid4();
		}
		return md5((string) microtime(true) . '|' . (string) wp_rand());
	}
}
