<?php
namespace WPNexusAI\DB;

if (!defined('ABSPATH')) {
	exit;
}

final class DB {

	public const DB_VERSION = 1;

	/**
	 * Returns table prefix for WPNexus tables.
	 * If plugin is network-activated on multisite, use base_prefix for shared tables.
	 */
	public static function table_prefix(): string {
		global $wpdb;

		$prefix = $wpdb->prefix;

		if (is_multisite()) {
			// If network activated, store centrally.
			if (!function_exists('is_plugin_active_for_network')) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			if (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network(WPNEXUS_AI_BASENAME)) {
				$prefix = $wpdb->base_prefix;
			}
		}

		return $prefix;
	}

	public static function table(string $suffix): string {
		return self::table_prefix() . 'wpnexus_' . $suffix;
	}

	public static function tables(): array {
		return [
			'targets'        => self::table('targets'),
			'keys'           => self::table('keys'),
			'registry'       => self::table('registry'),
			'mapping_terms'  => self::table('mapping_terms'),
			'jobs'           => self::table('jobs'),
			'license'        => self::table('license'),
		];
	}
}
