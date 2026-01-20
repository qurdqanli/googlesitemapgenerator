<?php
namespace WPNexusAIBridge\Core;

use WPNexusAIBridge\I18n\I18n;
use WPNexusAIBridge\Logging\Logger;
use WPNexusAIBridge\Security\TokenManager;
use WPNexusAIBridge\Security\Auth;
use WPNexusAIBridge\Multisite\Switcher;
use WPNexusAIBridge\API\Routes;
use WPNexusAIBridge\Admin\Admin;

if (!defined('ABSPATH')) {
	exit;
}

final class Plugin {

	private $logger;
	private $i18n;

	public function __construct() {
		$this->logger = Logger::instance();
		$this->i18n   = new I18n('wpnexus-ai-bridge', WPNEXUS_AI_BRIDGE_DIR, WPNEXUS_AI_BRIDGE_BASENAME);
	}

	public function init(): void {
		$this->logger->info('bridge.init.start', [
			'version' => WPNEXUS_AI_BRIDGE_VERSION,
		]);

		$this->i18n->register();

		// Ensure a service token exists (Bearer auth).
		TokenManager::ensure_exists();

		// Bearer token -> current user mapping (so capability checks work).
		Auth::register();

		// Multisite routing (X-WPNexus-Network-Site).
		Switcher::register();

		// Register REST routes.
		(new Routes())->init();

		// Minimal admin page (token display/regenerate).
		if (is_admin()) {
			(new Admin())->init();
		}

		add_action('init', function () {
			$this->logger->debug('bridge.wp.init', [
				'is_multisite' => is_multisite(),
				'is_admin'     => is_admin(),
			]);
		}, 1);

		$this->logger->info('bridge.init.done');
	}

	public function logger(): Logger {
		return $this->logger;
	}
}

