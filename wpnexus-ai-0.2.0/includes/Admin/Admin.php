<?php
namespace WPNexusAI\Admin;

use WPNexusAI\Admin\Screens\DashboardScreen;
use WPNexusAI\Admin\Screens\TargetsScreen;
use WPNexusAI\Admin\Screens\KeysScreen;
use WPNexusAI\Admin\Screens\RulesScreen;
use WPNexusAI\Admin\Screens\JobsScreen;
use WPNexusAI\Admin\Screens\SettingsScreen;
use WPNexusAI\Admin\Screens\BulkScreen;
use WPNexusAI\Logging\Logger;

if (!defined('ABSPATH')) { exit; }

final class Admin {

    public const CAPABILITY = 'manage_options';

    /** @var Logger */
    private $logger;

    /** @var array<string, object> */
    private $screens = [];

    public function __construct() {
        $this->logger = Logger::instance();

        $this->screens = [
            'wpnexus-ai'           => new DashboardScreen(),
            'wpnexus-ai-targets'   => new TargetsScreen(),
            'wpnexus-ai-keys'      => new KeysScreen(),
            'wpnexus-ai-rules'     => new RulesScreen(),
            'wpnexus-ai-jobs'      => new JobsScreen(),
            'wpnexus-ai-settings'  => new SettingsScreen(),
            'wpnexus-ai-bulk'      => new BulkScreen(),
        ];
    }

    public function register(): void {
        add_action('admin_menu', [$this, 'menu']);
    }

    public function menu(): void {
        $cap = self::CAPABILITY;

        add_menu_page(
            'WPNexus AI',
            'WPNexus AI',
            $cap,
            'wpnexus-ai',
            [$this->screens['wpnexus-ai'], 'render'],
            'dashicons-randomize',
            56
        );

        add_submenu_page('wpnexus-ai', 'Targets', 'Targets', $cap, 'wpnexus-ai-targets', [$this->screens['wpnexus-ai-targets'], 'render']);
        add_submenu_page('wpnexus-ai', 'AI Keys', 'AI Keys', $cap, 'wpnexus-ai-keys', [$this->screens['wpnexus-ai-keys'], 'render']);
        add_submenu_page('wpnexus-ai', 'Rules', 'Rules', $cap, 'wpnexus-ai-rules', [$this->screens['wpnexus-ai-rules'], 'render']);
        add_submenu_page('wpnexus-ai', 'Jobs', 'Jobs', $cap, 'wpnexus-ai-jobs', [$this->screens['wpnexus-ai-jobs'], 'render']);
        add_submenu_page('wpnexus-ai', 'Bulk Sync', 'Bulk Sync', $cap, 'wpnexus-ai-bulk', [$this->screens['wpnexus-ai-bulk'], 'render']);
        add_submenu_page('wpnexus-ai', 'Settings', 'Settings', $cap, 'wpnexus-ai-settings', [$this->screens['wpnexus-ai-settings'], 'render']);
    }
}
