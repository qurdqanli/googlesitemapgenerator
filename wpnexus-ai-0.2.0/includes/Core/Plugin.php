<?php
namespace WPNexusAI\Core;

use WPNexusAI\I18n\I18n;
use WPNexusAI\Logging\Logger;
use WPNexusAI\Admin\Admin;
use WPNexusAI\Queue\JobRunner;
use WPNexusAI\Rules\TriggerManager;

if (!defined('ABSPATH')) { exit; }

final class Plugin {

    private static $instance;

    /** @var Logger */
    private $logger;

    /** @var Admin */
    private $admin;

    /** @var TriggerManager */
    private $triggers;

    public static function instance(): self {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->logger = Logger::instance();
    }

    public function boot(): void {
        // Load i18n helper function t().
        require_once WPNEXUS_AI_DIR . 'includes/I18n/functions.php';
        I18n::instance()->load();

        $this->logger->info('core.init.start', ['version' => WPNEXUS_AI_VERSION]);

        // Queue runner hooks.
        JobRunner::register();

        // Admin.
        if (is_admin()) {
            $this->admin = new Admin();
            $this->admin->register();
        }

        // Auto-pilot triggers.
        $this->triggers = new TriggerManager();
        $this->triggers->register();

        $this->logger->info('core.init.done', ['is_admin' => is_admin()]);
    }
}
