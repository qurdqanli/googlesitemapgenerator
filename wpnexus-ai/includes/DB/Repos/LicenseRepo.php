<?php
namespace WPNexusAI\DB\Repos;

use WPNexusAI\DB\DB;

if (!defined('ABSPATH')) {
	exit;
}

final class LicenseRepo extends Repo {

	public function table(): string {
		return DB::table('license');
	}

	/**
	 * @return array<string,mixed>
	 */
	public function get_row(): array {
		global $wpdb;

		// Single-row table design
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row("SELECT * FROM {$this->table()} ORDER BY id ASC LIMIT 1", ARRAY_A);

		return is_array($row) ? $row : [];
	}

	/**
	 * @param array<string,mixed> $fields
	 */
	public function upsert(array $fields): bool {
		global $wpdb;

		$now = current_time('mysql', true);

		$defaults = [
			'opt_in'           => 0,
			'purchase_code'    => '',
			'token'            => '',
			'entitlements_json'=> '{}',
			'last_check_at'    => null,
			'grace_until'      => null,
		];

		$fields = array_merge($defaults, $fields);

		$opt_in = !empty($fields['opt_in']) ? 1 : 0;

		$purchase_code = is_string($fields['purchase_code']) ? $fields['purchase_code'] : '';
		$token         = is_string($fields['token']) ? $fields['token'] : '';
		$ent_json      = is_string($fields['entitlements_json']) ? $fields['entitlements_json'] : '{}';

		$last_check_at = !empty($fields['last_check_at']) ? (string) $fields['last_check_at'] : null;
		$grace_until   = !empty($fields['grace_until']) ? (string) $fields['grace_until'] : null;

		$current = $this->get_row();

		if (!empty($current['id'])) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$ok = (bool) $wpdb->update(
				$this->table(),
				[
					'opt_in'            => $opt_in,
					'purchase_code'     => $purchase_code,
					'token'             => $token,
					'entitlements_json' => $ent_json,
					'last_check_at'     => $last_check_at,
					'grace_until'       => $grace_until,
					'updated_at'        => $now,
				],
				['id' => (int) $current['id']],
				['%d','%s','%s','%s','%s','%s','%s'],
				['%d']
			);

			$this->logger->info('license.update', ['ok' => $ok]);
			return $ok;
		}

		// Insert first row
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = (bool) $wpdb->insert(
			$this->table(),
			[
				'opt_in'            => $opt_in,
				'purchase_code'     => $purchase_code,
				'token'             => $token,
				'entitlements_json' => $ent_json,
				'last_check_at'     => $last_check_at,
				'grace_until'       => $grace_until,
				'created_at'        => $now,
				'updated_at'        => $now,
			],
			['%d','%s','%s','%s','%s','%s','%s','%s']
		);

		$this->logger->info('license.insert', ['ok' => $ok]);
		return $ok;
	}
}

