<?php
namespace WPNexusAIBridge\Core;

use WPNexusAIBridge\Logging\Logger;

if (!defined('ABSPATH')) {
	exit;
}

final class Deactivator {

	public static function deactivate(): void {
		$logger = Logger::instance();
		$logger->info('bridge.deactivate.start');
		$logger->info('bridge.deactivate.done');
	}
}
