<?php
/**
 * Plugin Name: WPNexus AI (Core)
 * Description: Multi-site AI syndication: translate/sync posts & products to remote WordPress targets via WPNexus AI Bridge.
 * Version: 0.2.0
 * Author: Emblem Technologies
 * Text Domain: wpnexus-ai
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) { exit; }

define('WPNEXUS_AI_VERSION', '0.2.0');
define('WPNEXUS_AI_FILE', __FILE__);
define('WPNEXUS_AI_DIR', plugin_dir_path(__FILE__));
define('WPNEXUS_AI_URL', plugin_dir_url(__FILE__));

require_once WPNEXUS_AI_DIR . 'includes/Core/Autoloader.php';

\WPNexusAI\Core\Autoloader::register();

add_action('plugins_loaded', function () {
    \WPNexusAI\Core\Plugin::instance()->boot();
}, 5);

register_activation_hook(__FILE__, function () {
    \WPNexusAI\Install\Installer::activate();
});

register_deactivation_hook(__FILE__, function () {
    \WPNexusAI\Install\Installer::deactivate();
});
