<?php
/**
 * Plugin Name: WPNexus AI Bridge
 * Description: Target-side Bridge plugin providing stable universal endpoints for WPNexus AI Core (multisite, multilingual, SEO, taxonomy, media, upsert/delete).
 * Version: 0.1.2
 * Author: Emblem Syndicate
 * Text Domain: wpnexus-ai-bridge
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
	exit;
}

define('WPNEXUS_AI_BRIDGE_VERSION', '0.1.2');
define('WPNEXUS_AI_BRIDGE_FILE', __FILE__);
define('WPNEXUS_AI_BRIDGE_BASENAME', plugin_basename(__FILE__));
define('WPNEXUS_AI_BRIDGE_DIR', plugin_dir_path(__FILE__));
define('WPNEXUS_AI_BRIDGE_URL', plugin_dir_url(__FILE__));

require_once WPNEXUS_AI_BRIDGE_DIR . 'includes/Core/Autoloader.php';

\WPNexusAIBridge\Core\Autoloader::register('WPNexusAIBridge\\', WPNEXUS_AI_BRIDGE_DIR . 'includes/');

require_once WPNEXUS_AI_BRIDGE_DIR . 'includes/I18n/t.php';

function wpnexus_ai_bridge(): \WPNexusAIBridge\Core\Plugin {
	static $instance = null;

	if ($instance === null) {
		$instance = new \WPNexusAIBridge\Core\Plugin();
	}

	return $instance;
}

register_activation_hook(__FILE__, function () {
	require_once WPNEXUS_AI_BRIDGE_DIR . 'includes/Core/Activator.php';
	\WPNexusAIBridge\Core\Activator::activate();
});

register_deactivation_hook(__FILE__, function () {
	require_once WPNEXUS_AI_BRIDGE_DIR . 'includes/Core/Deactivator.php';
	\WPNexusAIBridge\Core\Deactivator::deactivate();
});

add_action('plugins_loaded', function () {
	wpnexus_ai_bridge()->init();
}, 5);
