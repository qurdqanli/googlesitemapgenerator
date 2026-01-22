<?php
namespace WPNexusAI\DB\Repos;

use WPNexusAI\DB\DB;

if (!defined('ABSPATH')) {
	exit;
}

final class JobsRepo extends Repo {

	public function table(): string {
		return DB::table('jobs');
	}

	/**
	 * Create queued job. Returns job_id (0 on failure).
	 *
	 * @param array<string,mixed> $payload
	 */
	public function create(string $type, array $payload, ?int $next_run_ts = null): int {
		global $wpdb;

		$type = sanitize_key($type);
		if ($type === '') {
			$this->logger->warning('jobs.create.invalid_type', [
				'type' => $type,
			]);
			return 0;
		}

		$now  = current_time('mysql', true);

		$payload_json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (!is_string($payload_json) || $payload_json === '') {
			$payload_json = '{}';
		}

		$next_run_at = null;
		if (is_int($next_run_ts) && $next_run_ts > 0) {
			$next_run_at = gmdate('Y-m-d H:i:s', $next_run_ts);
		}

		$data = [
			'type'        => $type,
			'payload'     => $payload_json,
			'status'      => 'queued',
			'attempts'    => 0,
			'next_run_at' => $next_run_at,
			'last_error'  => null,
			'created_at'  => $now,
			'updated_at'  => $now,
		];

		$formats = ['%s','%s','%s','%d','%s','%s','%s','%s'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = (bool) $wpdb->insert($this->table(), $data, $formats);

		$id = $ok ? (int) $wpdb->insert_id : 0;

		$this->logger->info('jobs.create', [
			'id'      => $id,
			'type'    => $type,
			'nextRun' => $next_run_at,
			'ok' => $ok,
			'rows' => $affected,
		]);

		return $id;
	}

	public function get(int $id): ?array {
		global $wpdb;

		$id = (int) $id;
		if ($id <= 0) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id),
			ARRAY_A
		);

		return is_array($row) ? $row : null;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function due(int $limit = 20): array {
		global $wpdb;

		$limit = max(1, min(200, (int) $limit));
		$now   = current_time('mysql', true);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()}
				 WHERE status = %s AND (next_run_at IS NULL OR next_run_at <= %s)
				 ORDER BY COALESCE(next_run_at, created_at) ASC
				 LIMIT %d",
				'queued',
				$now,
				$limit
			),
			ARRAY_A
		);

		return is_array($rows) ? $rows : [];
	}

	public function mark_running(int $id): bool {
		global $wpdb;

		$id  = (int) $id;
		$now = current_time('mysql', true);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$affected = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table()}
				 SET status = %s, attempts = attempts + 1, updated_at = %s
				 WHERE id = %d AND status = %s",
				'running',
				$now,
				$id,
				'queued'
			)
		);

		$ok = ($affected === 1);

		$this->logger->debug('jobs.mark_running', ['id' => $id, 'ok' => $ok]);
		return $ok;
	}

	public function mark_done(int $id): bool {
		global $wpdb;

		$id  = (int) $id;
		$now = current_time('mysql', true);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$affected = $wpdb->update(
			$this->table(),
			[
				'status'     => 'done',
				'last_error' => null,
				'updated_at' => $now,
			],
			['id' => $id],
			['%s','%s','%s'],
			['%d']
		);

		$ok = ($affected !== false);

		$this->logger->info('jobs.mark_done', ['id' => $id, 'ok' => $ok]);
		return $ok;
	}

	public function mark_failed(int $id, string $error, string $status = 'failed'): bool {
		global $wpdb;

		$id     = (int) $id;
		$now    = current_time('mysql', true);
		$status = in_array($status, ['failed','needs_input'], true) ? $status : 'failed';

		$error = wp_strip_all_tags($error);
		if (strlen($error) > 8000) {
			$error = substr($error, 0, 8000);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$affected = $wpdb->update(
			$this->table(),
			[
				'status'     => $status,
				'last_error' => $error,
				'updated_at' => $now,
			],
			['id' => $id],
			['%s','%s','%s'],
			['%d']
		);

		$ok = ($affected !== false);

		$this->logger->warning('jobs.mark_failed', [
			'id'     => $id,
			'status' => $status,
			'ok' => $ok,
			'rows' => $affected,
		]);

		return $ok;
	}

	public function reschedule(int $id, int $next_run_ts, ?string $error = null): bool {
		global $wpdb;

		$id  = (int) $id;
		$now = current_time('mysql', true);

		$next_run_ts = max(1, (int) $next_run_ts);
		$dt = gmdate('Y-m-d H:i:s', $next_run_ts);

		$fields = [
			'status'      => 'queued',
			'next_run_at' => $dt,
			'updated_at'  => $now,
		];
		$formats = ['%s','%s','%s'];

		if (is_string($error) && $error !== '') {
			$error = wp_strip_all_tags($error);
			if (strlen($error) > 8000) {
				$error = substr($error, 0, 8000);
			}
			$fields['last_error'] = $error;
			$formats[] = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$affected = $wpdb->update(
			$this->table(),
			$fields,
			['id' => $id],
			$formats,
			['%d']
		);

		$ok = ($affected !== false);

		$this->logger->info('jobs.reschedule', [
			'id'      => $id,
			'nextRun' => $dt,
			'ok' => $ok,
			'rows' => $affected,
		]);

		return $ok;
	}

	/**
	 * Update job payload (JSON) safely.
	 *
	 * @param array<string,mixed> $payload
	 */
	public function update_payload(int $id, array $payload): bool {
		global $wpdb;

		$id  = (int) $id;
		if ($id <= 0) {
			return false;
		}

		$now = current_time('mysql', true);

		$payload_json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($payload_json) || $payload_json === '') {
			$payload_json = '{}';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->update(
			$this->table(),
			[
				'payload'    => $payload_json,
				'updated_at' => $now,
			],
			['id' => $id],
			['%s','%s'],
			['%d']
		);

		$ok = ($rows !== false);
		$affected = is_int($rows) ? (int) $rows : 0;

		$this->logger->debug('jobs.update_payload', [
			'id' => $id,
			'ok' => $ok,
			'rows' => $affected,
		]);

		return $ok;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function decode_payload(array $job_row): array {
		$raw = isset($job_row['payload']) ? (string) $job_row['payload'] : '';
		$dec = json_decode($raw, true);
		return is_array($dec) ? $dec : [];
	}
}

