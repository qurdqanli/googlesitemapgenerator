<?php
/**
 * Plugin Name: WPNexus AI Bridge
 * Description: Receiver bridge for WPNexus AI Core: accepts upserts (posts, taxonomies, media) from authorized source sites.
 * Version: 0.2.0
 * Author: Emblem Technologies
 * Text Domain: wpnexus-ai-bridge
 */

if (!defined('ABSPATH')) { exit; }

define('WPNEXUS_AI_BRIDGE_FILE', __FILE__);
define('WPNEXUS_AI_BRIDGE_PATH', plugin_dir_path(__FILE__));
define('WPNEXUS_AI_BRIDGE_URL', plugin_dir_url(__FILE__));

require_once WPNEXUS_AI_BRIDGE_PATH . 'includes/Core/Autoloader.php';

\WPNexusAIBridge\Core\Autoloader::register();

add_action('plugins_loaded', function () {
    \WPNexusAIBridge\Core\Plugin::instance()->boot();
});

register_activation_hook(__FILE__, function () {
    \WPNexusAIBridge\Core\Plugin::instance()->activate();
});
