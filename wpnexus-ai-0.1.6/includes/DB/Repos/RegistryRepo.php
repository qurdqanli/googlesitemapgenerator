<?php
namespace WPNexusAI\DB\Repos;

use WPNexusAI\DB\DB;

if (!defined('ABSPATH')) {
	exit;
}

final class RegistryRepo extends Repo {

	public function table(): string {
		return DB::table('registry');
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function get_link(int $source_post_id, int $target_id, string $language_code): ?array {
		global $wpdb;

		$source_post_id = (int) $source_post_id;
		$target_id      = (int) $target_id;
		$language_code  = sanitize_key($language_code);

		if ($source_post_id <= 0 || $target_id <= 0 || $language_code === '') {
			return null;
		}

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE source_post_id = %d AND target_id = %d AND language_code = %s",
				$source_post_id,
				$target_id,
				$language_code
			),
			ARRAY_A
		);

		return is_array($row) ? $row : null;
	}

	/**
	 * Upsert registry link (UNIQUE: source_post_id,target_id,language_code).
	 *
	 * @param array<string,mixed> $fields remote_post_id,url,content_hash,state,last_error
	 */
	public function upsert_link(int $source_post_id, int $target_id, string $language_code, array $fields): bool {
		global $wpdb;

		$source_post_id = (int) $source_post_id;
		$target_id      = (int) $target_id;
		$language_code  = sanitize_key($language_code);

		if ($source_post_id <= 0 || $target_id <= 0 || $language_code === '') {
			return false;
		}

		$now = current_time('mysql', true);

		$remote_post_id = isset($fields['remote_post_id']) ? (string) $fields['remote_post_id'] : null;
		$url            = isset($fields['url']) ? (string) $fields['url'] : null;
		$content_hash   = isset($fields['content_hash']) ? (string) $fields['content_hash'] : null;
		$state          = isset($fields['state']) ? sanitize_key((string) $fields['state']) : 'unlinked';
		$last_error     = isset($fields['last_error']) ? (string) $fields['last_error'] : null;

		if ($last_error !== null) {
			$last_error = wp_strip_all_tags($last_error);
			if (strlen($last_error) > 8000) {
				$last_error = substr($last_error, 0, 8000);
			}
		}

		$table = $this->table();

		// Use ON DUPLICATE KEY UPDATE for atomic-ish upsert.
		$sql = "
			INSERT INTO {$table}
				(source_post_id, target_id, language_code, remote_post_id, url, content_hash, state, last_error, created_at, updated_at)
			VALUES
				(%d, %d, %s, %s, %s, %s, %s, %s, %s, %s)
			ON DUPLICATE KEY UPDATE
				remote_post_id = VALUES(remote_post_id),
				url           = VALUES(url),
				content_hash  = VALUES(content_hash),
				state         = VALUES(state),
				last_error    = VALUES(last_error),
				updated_at    = VALUES(updated_at)
		";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = (bool) $wpdb->query(
			$wpdb->prepare(
				$sql,
				$source_post_id,
				$target_id,
				$language_code,
				$remote_post_id,
				$url,
				$content_hash,
				$state,
				$last_error,
				$now,
				$now
			)
		);

		$this->logger->debug('registry.upsert_link', [
			'source_post_id' => $source_post_id,
			'target_id'      => $target_id,
			'lang'           => $language_code,
			'state'          => $state,
			'ok'             => $ok,
		]);

		return $ok;
	}

	public function touch(int $source_post_id, int $target_id, string $language_code): bool {
		global $wpdb;

		$source_post_id = (int) $source_post_id;
		$target_id      = (int) $target_id;
		$language_code  = sanitize_key($language_code);

		if ($source_post_id <= 0 || $target_id <= 0 || $language_code === '') {
			return false;
		}

		$now = current_time('mysql', true);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$affected = $wpdb->update(
			$this->table(),
			['updated_at' => $now],
			[
				'source_post_id' => $source_post_id,
				'target_id'      => $target_id,
				'language_code'  => $language_code,
			],
			['%s'],
			['%d','%d','%s']
		);

		return ($affected !== false);
	}
 
         	/**
	 * List registry links for reconcile.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function list_for_reconcile(int $limit = 50, int $offset = 0, int $target_id = 0): array {
		global $wpdb;

		$limit  = max(1, min(200, (int) $limit));
		$offset = max(0, (int) $offset);
		$target_id = (int) $target_id;

		$table = $this->table();

		$where = "WHERE state IN ('linked','failed')";
		$args  = [];

		if ($target_id > 0) {
			$where .= " AND target_id = %d";
			$args[] = $target_id;
		}

		$sql = "SELECT * FROM {$table} {$where} ORDER BY updated_at ASC LIMIT %d OFFSET %d";
		$args[] = $limit;
		$args[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);

		return is_array($rows) ? $rows : [];
	}

}

