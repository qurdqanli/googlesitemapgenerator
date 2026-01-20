<?php
namespace WPNexusAI\DB\Repos;

use WPNexusAI\DB\DB;
use WPNexusAI\Security\Crypto;

if (!defined('ABSPATH')) {
	exit;
}

final class KeysRepo extends Repo {

	public function table(): string {
		return DB::table('keys');
	}

	/**
	 * @return array<int, array<string,mixed>>
	 */
	public function list(int $limit = 200): array {
		global $wpdb;
		$limit = max(1, min(500, $limit));
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT " . (int) $limit, ARRAY_A);
		return is_array($rows) ? $rows : [];
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function get(int $id): ?array {
		global $wpdb;
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id), ARRAY_A);
		return is_array($row) ? $row : null;
	}

	/**
	 * @param string $provider
	 * @return array<int, array<string,mixed>>
	 */
	public function list_by_provider(string $provider, int $limit = 500): array {
		global $wpdb;
		$provider = sanitize_key($provider);
		$limit = max(1, min(1000, $limit));
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$table} WHERE provider=%s ORDER BY id DESC LIMIT " . (int) $limit, $provider),
			ARRAY_A
		);

		return is_array($rows) ? $rows : [];
	}

	/**
	 * Create key. Returns id.
	 */
	public function create(string $provider, string $plain_key, bool $is_active = true): int {
		global $wpdb;

		$provider = sanitize_key($provider);
		$plain_key = trim($plain_key);

		$now = current_time('mysql', true);

		$key_cipher = Crypto::encrypt($plain_key);
		if (!is_string($key_cipher) || $key_cipher === '') {
			$this->logger->error('keys.create.encrypt_failed', ['provider' => $provider]);
			return 0;
		}

		$this->logger->info('keys.create.start', [
			'provider' => $provider,
			'is_active' => $is_active,
		]);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$this->table(),
			[
				'provider' => $provider,
				'key_cipher' => $key_cipher,
				'is_active' => $is_active ? 1 : 0,
				'cooldown_until' => null,
				'last_used_at' => null,
				'success_count' => 0,
				'fail_count' => 0,
				'rate_429_count' => 0,
				'created_at' => $now,
				'updated_at' => $now,
			],
			['%s','%s','%d','%s','%s','%d','%d','%d','%s','%s']
		);

		$id = (int) $wpdb->insert_id;

		$this->logger->info('keys.create.done', [
			'id' => $id,
			'provider' => $provider,
		]);

		return $id;
	}

	public function delete(int $id): bool {
		global $wpdb;

		$this->logger->info('keys.delete.start', ['id' => $id]);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = (bool) $wpdb->delete($this->table(), ['id' => $id], ['%d']);

		$this->logger->info('keys.delete.done', ['id' => $id, 'ok' => $ok]);
		return $ok;
	}

	public function set_active(int $id, bool $active): bool {
		global $wpdb;
		$now = current_time('mysql', true);

		$this->logger->info('keys.set_active.start', ['id' => $id, 'active' => $active]);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = (bool) $wpdb->update(
			$this->table(),
			[
				'is_active' => $active ? 1 : 0,
				'updated_at' => $now,
			],
			['id' => $id],
			['%d','%s'],
			['%d']
		);

		$this->logger->info('keys.set_active.done', ['id' => $id, 'ok' => $ok]);
		return $ok;
	}

	public function update_key(int $id, string $plain_key): bool {
		global $wpdb;
		$plain_key = trim($plain_key);

		$key_cipher = Crypto::encrypt($plain_key);
		if (!is_string($key_cipher) || $key_cipher === '') {
			$this->logger->error('keys.update.encrypt_failed', ['id' => $id]);
			return false;
		}

		$now = current_time('mysql', true);

		$this->logger->info('keys.update.start', ['id' => $id]);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = (bool) $wpdb->update(
			$this->table(),
			[
				'key_cipher' => $key_cipher,
				'updated_at' => $now,
			],
			['id' => $id],
			['%s','%s'],
			['%d']
		);

		$this->logger->info('keys.update.done', ['id' => $id, 'ok' => $ok]);
		return $ok;
	}

	public function mark_used(int $id): void {
		global $wpdb;
		$now = current_time('mysql', true);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$this->table(),
			[
				'last_used_at' => $now,
				'updated_at' => $now,
			],
			['id' => $id],
			['%s','%s'],
			['%d']
		);
	}

	public function record_success(int $id): void {
		global $wpdb;
		$now = current_time('mysql', true);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table()} SET success_count = success_count + 1, updated_at=%s WHERE id=%d",
				$now,
				$id
			)
		);
	}

	public function record_fail(int $id): void {
		global $wpdb;
		$now = current_time('mysql', true);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table()} SET fail_count = fail_count + 1, updated_at=%s WHERE id=%d",
				$now,
				$id
			)
		);
	}

	/**
	 * Increment 429 counter and set cooldown_until using exponential backoff.
	 * backoff_seconds = min(3600, 30 * 2^min(rate_429_count, 7))
	 */
	public function record_429_and_cooldown(int $id): int {
		global $wpdb;

		$row = $this->get($id);
		$current_429 = is_array($row) ? (int) ($row['rate_429_count'] ?? 0) : 0;
		$next_429 = $current_429 + 1;

		$exp = min($next_429, 7);
		$backoff = (int) min(3600, 30 * (2 ** $exp)); // 60s..3840s capped 3600

		$until_ts = time() + $backoff;
		$cooldown_until = gmdate('Y-m-d H:i:s', $until_ts);
		$now = current_time('mysql', true);

		$this->logger->info('keys.cooldown.set', [
			'id' => $id,
			'backoff_seconds' => $backoff,
			'cooldown_until' => $cooldown_until,
			'rate_429_next' => $next_429,
		]);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table()} 
				 SET rate_429_count = rate_429_count + 1,
				     cooldown_until = %s,
				     updated_at = %s
				 WHERE id=%d",
				$cooldown_until,
				$now,
				$id
			)
		);

		return $backoff;
	}

	/**
	 * Return active keys that are not cooling down.
	 * @return array<int, array<string,mixed>>
	 */
	public function list_available(string $provider, int $limit = 200): array {
		global $wpdb;
		$provider = sanitize_key($provider);
		$limit = max(1, min(500, $limit));
		$table = $this->table();

		$now_gmt = gmdate('Y-m-d H:i:s');

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE provider=%s
				   AND is_active=1
				   AND (cooldown_until IS NULL OR cooldown_until <= %s)
				 ORDER BY id DESC
				 LIMIT " . (int) $limit,
				$provider,
				$now_gmt
			),
			ARRAY_A
		);

		return is_array($rows) ? $rows : [];
	}

	public function count_active(string $provider): int {
		global $wpdb;
		$provider = sanitize_key($provider);
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$n = $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE provider=%s AND is_active=1", $provider));
		return (int) $n;
	}

	public function count_available(string $provider): int {
		global $wpdb;
		$provider = sanitize_key($provider);
		$table = $this->table();
		$now_gmt = gmdate('Y-m-d H:i:s');

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$n = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(1) FROM {$table}
				 WHERE provider=%s AND is_active=1
				   AND (cooldown_until IS NULL OR cooldown_until <= %s)",
				$provider,
				$now_gmt
			)
		);

		return (int) $n;
	}

	public function decrypt_key(?string $cipher): ?string {
		if (!$cipher) {
			return null;
		}
		$plain = Crypto::decrypt($cipher);
		if (!is_string($plain) || $plain === '') {
			return null;
		}
		return $plain;
	}

	/**
	 * Best-effort duplicate check by decrypting provider keys (ok for small sets).
	 */
	public function exists_plain(string $provider, string $plain_key): bool {
		$provider = sanitize_key($provider);
		$plain_key = trim($plain_key);

		$rows = $this->list_by_provider($provider, 1000);
		foreach ($rows as $r) {
			$existing = $this->decrypt_key($r['key_cipher'] ?? null);
			if (is_string($existing) && hash_equals($existing, $plain_key)) {
				return true;
			}
		}
		return false;
	}
}

