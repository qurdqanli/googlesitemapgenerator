<?php
/**
 * Plugin Name: WPNexus AI
 * Description: Syndicate content from a source WordPress site to multiple target sites with translation, mapping, SEO, and queued sync (Core plugin).
 * Version: 0.1.0
 * Author: Emblem Syndicate
 * Text Domain: wpnexus-ai
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
	exit;
}

define('WPNEXUS_AI_VERSION', '0.1.0');
define('WPNEXUS_AI_FILE', __FILE__);
define('WPNEXUS_AI_BASENAME', plugin_basename(__FILE__));
define('WPNEXUS_AI_DIR', plugin_dir_path(__FILE__));
define('WPNEXUS_AI_URL', plugin_dir_url(__FILE__));

require_once WPNEXUS_AI_DIR . 'includes/Core/Autoloader.php';

\WPNexusAI\Core\Autoloader::register('WPNexusAI\\', WPNEXUS_AI_DIR . 'includes/');

require_once WPNEXUS_AI_DIR . 'includes/I18n/t.php';

function wpnexus_ai(): \WPNexusAI\Core\Plugin {
	static $instance = null;

	if ($instance === null) {
		$instance = new \WPNexusAI\Core\Plugin();
	}

	return $instance;
}

register_activation_hook(__FILE__, function () {
	require_once WPNEXUS_AI_DIR . 'includes/Core/Activator.php';
	\WPNexusAI\Core\Activator::activate();
});

register_deactivation_hook(__FILE__, function () {
	require_once WPNEXUS_AI_DIR . 'includes/Core/Deactivator.php';
	\WPNexusAI\Core\Deactivator::deactivate();
});

add_action('plugins_loaded', function () {
	wpnexus_ai()->init();
}, 5);
