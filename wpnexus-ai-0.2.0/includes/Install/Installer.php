<?php
namespace WPNexusAI\Install;

use WPNexusAI\DB\Schema;
use WPNexusAI\Logging\Logger;

if (!defined('ABSPATH')) { exit; }

final class Installer {

    public static function activate(): void {
        $logger = Logger::instance();
        $logger->info('install.activate.start', ['version' => WPNEXUS_AI_VERSION]);

        self::migrate();

        // Ensure runner schedule exists.
        \WPNexusAI\Queue\JobRunner::ensure_sweep();

        $logger->info('install.activate.done', ['schema' => Schema::version()]);
    }

    public static function deactivate(): void {
        // Keep data; just unschedule sweeps.
        \WPNexusAI\Queue\JobRunner::disable_sweep();
    }

    public static function migrate(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach (Schema::table_sql() as $sql) {
            dbDelta($sql);
        }
        update_option('wpnexus_ai_schema_version', Schema::version(), true);
    }
}
