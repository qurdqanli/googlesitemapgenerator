<?php
namespace WPNexusAI\DB;

if (!defined('ABSPATH')) { exit; }

final class Schema {

    public static function version(): string {
        return '2026-01-22-2';
    }

    /** @return array<int, string> */
    public static function table_sql(): array {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $targets = $wpdb->prefix . 'wpnexus_ai_targets';
        $keys    = $wpdb->prefix . 'wpnexus_ai_keys';
        $rules   = $wpdb->prefix . 'wpnexus_ai_rules';
        $jobs    = $wpdb->prefix . 'wpnexus_ai_jobs';

        return [
            "CREATE TABLE {$targets} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(190) NOT NULL,
                base_url VARCHAR(255) NOT NULL,
                token_enc LONGTEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                settings_json LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY is_active (is_active)
            ) {$charset};",

            "CREATE TABLE {$keys} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                provider VARCHAR(50) NOT NULL,
                label VARCHAR(190) NOT NULL,
                model VARCHAR(120) NULL,
                key_enc LONGTEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY provider (provider),
                KEY is_active (is_active)
            ) {$charset};",

            "CREATE TABLE {$rules} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(190) NOT NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                source_post_types VARCHAR(255) NOT NULL,
                source_taxonomy VARCHAR(50) NULL,
                source_term_ids_json LONGTEXT NULL,
                source_author_ids_json LONGTEXT NULL,
                trigger_statuses VARCHAR(255) NOT NULL,
                target_ids_json LONGTEXT NULL,
                target_category_map_json LONGTEXT NULL,
                translate_taxonomies TINYINT(1) NOT NULL DEFAULT 1,
                persona VARCHAR(50) NOT NULL DEFAULT 'neutral',
                custom_prompt LONGTEXT NULL,
                internal_links_json LONGTEXT NULL,
                image_mode VARCHAR(20) NOT NULL DEFAULT 'keep',
                image_prompt LONGTEXT NULL,
                meta_map_json LONGTEXT NULL,
                translate_meta TINYINT(1) NOT NULL DEFAULT 0,
                acf_map_json LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY is_enabled (is_enabled)
            ) {$charset};",

            "CREATE TABLE {$jobs} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                type VARCHAR(50) NOT NULL,
                status VARCHAR(20) NOT NULL,
                payload_json LONGTEXT NOT NULL,
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                last_error LONGTEXT NULL,
                scheduled_at DATETIME NULL,
                as_action_id BIGINT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY status (status),
                KEY type (type)
            ) {$charset};",
        ];
    }
}

