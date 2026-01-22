<?php
namespace WPNexusAIBridge\Core;

use WPNexusAIBridge\Logging\Logger;
use WPNexusAIBridge\Security\TokenManager;

if (!defined('ABSPATH')) {
	exit;
}

final class Activator {

	public static function activate(): void {
		$logger = Logger::instance();
		$logger->info('bridge.activate.start', [
			'version' => defined('WPNEXUS_AI_BRIDGE_VERSION') ? WPNEXUS_AI_BRIDGE_VERSION : null,
		]);

		add_option('wpnexus_ai_bridge_installed_at', time(), '', false);

		// Create initial token (shown in admin Settings page once).
		TokenManager::ensure_exists();

		$logger->info('bridge.activate.done');
	}
}

