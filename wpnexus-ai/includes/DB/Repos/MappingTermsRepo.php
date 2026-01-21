<?php
namespace WPNexusAI\DB\Repos;

use WPNexusAI\DB\DB;

if (!defined('ABSPATH')) {
	exit;
}

final class MappingTermsRepo extends Repo {

	public function table(): string {
		return DB::table('mapping_terms');
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function get(int $target_id, string $taxonomy, int $source_term_id, string $language_code): ?array {
		global $wpdb;

		$target_id      = (int) $target_id;
		$taxonomy       = sanitize_key($taxonomy);
		$source_term_id = (int) $source_term_id;
		$language_code  = sanitize_key($language_code);

		if ($target_id <= 0 || $taxonomy === '' || $source_term_id <= 0 || $language_code === '') {
			return null;
		}

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE target_id = %d AND taxonomy = %s AND source_term_id = %d AND language_code = %s LIMIT 1",
				$target_id,
				$taxonomy,
				$source_term_id,
				$language_code
			),
			ARRAY_A
		);

		return is_array($row) ? $row : null;
	}

	public function upsert(
		int $target_id,
		string $taxonomy,
		int $source_term_id,
		string $language_code,
		int $target_term_id,
		string $slug
	): bool {
		global $wpdb;

		$target_id      = (int) $target_id;
		$taxonomy       = sanitize_key($taxonomy);
		$source_term_id = (int) $source_term_id;
		$language_code  = sanitize_key($language_code);
		$target_term_id = (int) $target_term_id;
		$slug           = sanitize_title($slug);

		if ($target_id <= 0 || $taxonomy === '' || $source_term_id <= 0 || $language_code === '' || $target_term_id <= 0) {
			return false;
		}

		$table = $this->table();
		$now   = current_time('mysql', true);

		$sql = "
			INSERT INTO {$table}
				(target_id, taxonomy, source_term_id, language_code, target_term_id, slug, created_at, updated_at)
			VALUES
				(%d, %s, %d, %s, %d, %s, %s, %s)
			ON DUPLICATE KEY UPDATE
				target_term_id = VALUES(target_term_id),
				slug           = VALUES(slug),
				updated_at     = VALUES(updated_at)
		";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = (bool) $wpdb->query(
			$wpdb->prepare(
				$sql,
				$target_id,
				$taxonomy,
				$source_term_id,
				$language_code,
				$target_term_id,
				$slug,
				$now,
				$now
			)
		);

		$this->logger->debug('mapping_terms.upsert', [
			'target_id'      => $target_id,
			'taxonomy'       => $taxonomy,
			'source_term_id' => $source_term_id,
			'target_term_id' => $target_term_id,
			'lang'           => $language_code,
			'ok'             => $ok,
		]);

		return $ok;
	}
}
