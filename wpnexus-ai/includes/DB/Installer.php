<?php
namespace WPNexusAI\DB;

use WPNexusAI\Logging\Logger;

if (!defined('ABSPATH')) {
	exit;
}

final class Installer {

	public static function install(): void {
		$logger = Logger::instance();
		$logger->info('db.install.start', [
			'target_version' => DB::DB_VERSION,
		]);

		self::run_migrations(true);

		$logger->info('db.install.done', [
			'db_version' => (int) get_option('wpnexus_ai_db_version', 0),
		]);
	}

	/**
	 * Lightweight upgrade check on each load (safe).
	 */
	public static function maybe_upgrade(): void {
		$current = (int) get_option('wpnexus_ai_db_version', 0);
		if ($current >= DB::DB_VERSION) {
			return;
		}

		$logger = Logger::instance();
		$logger->info('db.upgrade.needed', [
			'current' => $current,
			'target'  => DB::DB_VERSION,
		]);

		self::run_migrations(false);

		$logger->info('db.upgrade.done', [
			'db_version' => (int) get_option('wpnexus_ai_db_version', 0),
		]);
	}

	private static function run_migrations(bool $is_activation): void {
		$logger = Logger::instance();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$statements = Schema::statements();

		$logger->info('db.migrate.start', [
			'is_activation' => $is_activation,
			'tables'        => array_values(DB::tables()),
		]);

		foreach ($statements as $sql) {
			// dbDelta returns array of queries performed; not always reliable to parse.
			$logger->debug('db.migrate.statement', [
				'sql' => self::shorten_sql($sql),
			]);
			dbDelta($sql);
		}

		// Ensure license row exists (id=1)
		self::ensure_license_row();

		update_option('wpnexus_ai_db_version', DB::DB_VERSION, false);

		$logger->info('db.migrate.finish', [
			'db_version' => DB::DB_VERSION,
		]);
	}

	private static function ensure_license_row(): void {
		global $wpdb;

		$t = DB::tables();
		$table = $t['license'];

		$now = current_time('mysql', true);

		// If row not exists, insert id=1.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE id = %d", 1));

		if (empty($exists)) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$table,
				[
					'id'          => 1,
					'opt_in'      => 0,
					'updated_at'  => $now,
				],
				[
					'%d', '%d', '%s'
				]
			);
		}
	}

	private static function shorten_sql(string $sql): string {
		$one = preg_replace('/\s+/', ' ', trim($sql));
		return is_string($one) ? substr($one, 0, 220) . '...' : '';
	}
}
