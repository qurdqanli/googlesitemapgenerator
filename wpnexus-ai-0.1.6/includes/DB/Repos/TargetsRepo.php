<?php
namespace WPNexusAI\DB\Repos;

use WPNexusAI\DB\DB;
use WPNexusAI\Security\Crypto;

if (!defined('ABSPATH')) {
	exit;
}

final class TargetsRepo extends Repo {

	public function table(): string {
		return DB::table('targets');
	}

	/**
	 * Returns total number of targets.
	 */
	public function count_all(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$n = $wpdb->get_var("SELECT COUNT(1) FROM {$this->table()}");
		return (int) $n;
	}

	/**
	 * @return array<int, array<string,mixed>>
	 */
	public function list(int $limit = 100): array {
		global $wpdb;
		$limit = max(1, min(500, $limit));
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT " . (int) $limit, ARRAY_A);
		return is_array($rows) ? $rows : [];
	}

	/**
	 * Raw row (includes secrets_cipher).
	 * @return array<string,mixed>|null
	 */
	public function get(int $id): ?array {
		global $wpdb;
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id), ARRAY_A);
		return is_array($row) ? $row : null;
	}

	public function delete(int $id): bool {
		global $wpdb;

		$targets = $this->table();
		$registry = DB::table('registry');
		$mapping = DB::table('mapping_terms');

		$this->logger->info('targets.delete.start', ['id' => $id]);

		// Delete related mappings/registry to avoid orphans (lightweight).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->delete($registry, ['target_id' => $id], ['%d']);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->delete($mapping, ['target_id' => $id], ['%d']);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = (bool) $wpdb->delete($targets, ['id' => $id], ['%d']);

		$this->logger->info('targets.delete.done', ['id' => $id, 'ok' => $ok]);
		return $ok;
	}

	/**
	 * Create or update target. Returns target id.
	 *
	 * Secrets handling:
	 * - If secrets array is null -> keep existing secrets when updating (if provided)
	 * - If secrets array is []   -> clear secrets
	 *
	 * @param array<string,mixed> $data
	 * @param array<string,mixed>|null $secrets
	 */
	public function upsert(array $data, ?array $secrets): int {
		global $wpdb;
		$table = $this->table();

		$id = isset($data['id']) ? (int) $data['id'] : 0;

		$now = current_time('mysql', true);

		$base_url = $this->normalize_base_url((string) ($data['base_url'] ?? ''));
		$auth_method = sanitize_key((string) ($data['auth_method'] ?? ''));
		$auth_user = sanitize_text_field((string) ($data['auth_user'] ?? ''));
		$network_site_id = isset($data['network_site_id']) && $data['network_site_id'] !== '' ? (int) $data['network_site_id'] : null;

		$default_language = sanitize_text_field((string) ($data['default_language'] ?? 'auto'));
		if ($default_language === '') {
			$default_language = 'auto';
		}

		$fallback_language = sanitize_text_field((string) ($data['fallback_language'] ?? 'en'));
		if ($fallback_language === '') {
			$fallback_language = 'en';
		}

		$seo_canonical_mode = sanitize_key((string) ($data['seo_canonical_mode'] ?? 'self'));
		if (!in_array($seo_canonical_mode, ['self', 'source', 'custom'], true)) {
			$seo_canonical_mode = 'self';
		}
		$seo_canonical_custom = sanitize_text_field((string) ($data['seo_canonical_custom'] ?? ''));
		if ($seo_canonical_custom === '') {
			$seo_canonical_custom = null;
		}

		$status_default = sanitize_key((string) ($data['status_default'] ?? 'draft'));
		$allowed_status = ['draft', 'publish', 'pending', 'private'];
		if (!in_array($status_default, $allowed_status, true)) {
			$status_default = 'draft';
		}

		$flags_json = isset($data['flags_json']) && is_string($data['flags_json']) ? $data['flags_json'] : null;

		$secrets_cipher = null;

		if ($id > 0) {
			$existing = $this->get($id);
			if (!$existing) {
				$id = 0; // treat as create
			} else {
				$secrets_cipher = $existing['secrets_cipher'] ?? null;
			}
		}

		// secrets = null => keep existing
		// secrets = []   => clear
		// secrets = [...] => set
		if ($secrets !== null) {
			if (empty($secrets)) {
				$secrets_cipher = null;
			} else {
				$secrets_cipher = Crypto::encrypt(wp_json_encode($secrets));
			}
		}

		$row = [
			'base_url'            => $base_url,
			'auth_method'         => $auth_method ?: null,
			'auth_user'           => $auth_user ?: null,
			'secrets_cipher'      => $secrets_cipher,
			'network_site_id'     => $network_site_id,
			'default_language'    => $default_language,
			'fallback_language'   => $fallback_language,
			'seo_canonical_mode'  => $seo_canonical_mode,
			'seo_canonical_custom'=> $seo_canonical_custom,
			'status_default'      => $status_default,
			'flags_json'          => $flags_json,
			'updated_at'          => $now,
		];

		if ($id <= 0) {
			// Licensing enforcement (default off; enabled via filter)
			$lic = new \WPNexusAI\Licensing\LicensingService();
			$count = (int) $this->count_all();
			if (!$lic->can_add_target($count)) {
				$this->logger->warning('targets.limit.blocked', [
					'count' => $count,
					'limit' => $lic->targets_limit(),
				]);
				return 0; // or WP_Error if your code supports it
			}

			$row['created_at'] = $now;

			$this->logger->info('targets.create.start', ['base_url' => $base_url, 'auth_method' => $auth_method]);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$table,
				$row,
				[
					'%s','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s','%s','%s',
				]
			);

			$new_id = (int) $wpdb->insert_id;

			$this->logger->info('targets.create.done', ['id' => $new_id]);
			return $new_id;
		}

		$this->logger->info('targets.update.start', ['id' => $id]);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$table,
			$row,
			['id' => $id],
			[
				'%s','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s','%s',
			],
			['%d']
		);

		$this->logger->info('targets.update.done', ['id' => $id]);
		return $id;
	}

	public function normalize_base_url(string $url): string {
		$url = trim($url);
		$url = esc_url_raw($url);

		$parts = wp_parse_url($url);
		if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
			return $url;
		}

		$scheme = strtolower((string) $parts['scheme']);
		$host   = strtolower((string) $parts['host']);
		$port   = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
		$path   = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';

		return $scheme . '://' . $host . $port . $path;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function decode_secrets(?string $cipher): ?array {
		if (!$cipher) {
			return null;
		}
		$json = Crypto::decrypt($cipher);
		if (!is_string($json) || $json === '') {
			return null;
		}
		$data = json_decode($json, true);
		return is_array($data) ? $data : null;
	}
}
