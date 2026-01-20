<?php
namespace WPNexusAI\DB;

if (!defined('ABSPATH')) {
	exit;
}

final class Schema {

	/**
	 * @return string[] SQL statements suitable for dbDelta()
	 */
	public static function statements(): array {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$t = DB::tables();

		$targets = "CREATE TABLE {$t['targets']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			base_url VARCHAR(255) NOT NULL,
			auth_method VARCHAR(32) NULL,
			auth_user VARCHAR(128) NULL,
			secrets_cipher LONGTEXT NULL,
			network_site_id BIGINT(20) UNSIGNED NULL,
			default_language VARCHAR(32) NOT NULL DEFAULT 'auto',
			fallback_language VARCHAR(32) NOT NULL DEFAULT 'en',
			seo_canonical_mode VARCHAR(16) NOT NULL DEFAULT 'self',
			seo_canonical_custom VARCHAR(255) NULL,
			status_default VARCHAR(20) NOT NULL DEFAULT 'draft',
			flags_json LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY base_url (base_url(191)),
			KEY network_site_id (network_site_id)
		) $charset_collate;";

		$keys = "CREATE TABLE {$t['keys']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			provider VARCHAR(32) NOT NULL,
			key_cipher LONGTEXT NOT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			cooldown_until DATETIME NULL,
			last_used_at DATETIME NULL,
			success_count INT(11) NOT NULL DEFAULT 0,
			fail_count INT(11) NOT NULL DEFAULT 0,
			rate_429_count INT(11) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY provider (provider),
			KEY cooldown_until (cooldown_until),
			KEY is_active (is_active)
		) $charset_collate;";

		$registry = "CREATE TABLE {$t['registry']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			source_post_id BIGINT(20) UNSIGNED NOT NULL,
			target_id BIGINT(20) UNSIGNED NOT NULL,
			language_code VARCHAR(32) NOT NULL,
			remote_post_id VARCHAR(64) NULL,
			url VARCHAR(255) NULL,
			content_hash CHAR(64) NULL,
			state VARCHAR(20) NOT NULL DEFAULT 'unlinked',
			last_error LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_link (source_post_id, target_id, language_code),
			KEY target_id (target_id),
			KEY remote_post_id (remote_post_id),
			KEY state (state)
		) $charset_collate;";

		$mapping_terms = "CREATE TABLE {$t['mapping_terms']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			target_id BIGINT(20) UNSIGNED NOT NULL,
			taxonomy VARCHAR(64) NOT NULL,
			source_term_id BIGINT(20) UNSIGNED NOT NULL,
			language_code VARCHAR(32) NOT NULL,
			target_term_id BIGINT(20) UNSIGNED NULL,
			slug VARCHAR(200) NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_map (target_id, taxonomy, source_term_id, language_code),
			KEY taxonomy (taxonomy),
			KEY target_id (target_id)
		) $charset_collate;";

		$jobs = "CREATE TABLE {$t['jobs']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			type VARCHAR(20) NOT NULL,
			payload LONGTEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'queued',
			attempts INT(11) NOT NULL DEFAULT 0,
			next_run_at DATETIME NULL,
			last_error LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY status_next (status, next_run_at),
			KEY type (type)
		) $charset_collate;";

		$license = "CREATE TABLE {$t['license']} (
			id BIGINT(20) UNSIGNED NOT NULL,
			opt_in TINYINT(1) NOT NULL DEFAULT 0,
			purchase_code_cipher LONGTEXT NULL,
			token_cipher LONGTEXT NULL,
			entitlements_json LONGTEXT NULL,
			last_check_at DATETIME NULL,
			grace_until DATETIME NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		return [$targets, $keys, $registry, $mapping_terms, $jobs, $license];
	}
}
