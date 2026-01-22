<?php
namespace WPNexusAIBridge\Core;

use WPNexusAIBridge\API\Routes;
use WPNexusAIBridge\Admin\Admin;
use WPNexusAIBridge\Logging\Logger;
use WPNexusAIBridge\Security\TokenManager;

if (!defined('ABSPATH')) { exit; }

final class Plugin {

    /** @var self */
    private static $inst;

    /** @var Logger */
    private $logger;

    /** @var TokenManager */
    private $tokens;

    /** @var Routes */
    private $routes;

    /** @var Admin */
    private $admin;

    public static function instance(): self {
        if (!self::$inst) {
            self::$inst = new self();
        }
        return self::$inst;
    }

    private function __construct() {
        $this->logger = Logger::instance();
        $this->tokens = new TokenManager();
        $this->routes = new Routes($this->tokens);
        $this->admin = new Admin($this->tokens);
    }

    public function boot(): void {
        $this->logger->info('bridge.boot');
        $this->tokens->ensure();

        add_action('rest_api_init', [$this->routes, 'register']);
        if (is_admin()) {
            add_action('admin_menu', [$this->admin, 'menu']);
            add_action('admin_post_wpnexus_ai_bridge_rotate_token', [$this->admin, 'handle_rotate']);
            add_action('admin_post_wpnexus_ai_bridge_save_settings', [$this->admin, 'handle_settings']);
        }
    }

    public function activate(): void {
        $this->tokens->ensure();
    }
}
