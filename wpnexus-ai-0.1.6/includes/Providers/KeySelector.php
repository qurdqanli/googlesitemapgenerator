<?php
namespace WPNexusAI\Providers;

use WPNexusAI\DB\Repos\KeysRepo;
use WPNexusAI\Logging\Logger;
use WPNexusAI\Util\Lock;
use WP_Error;

if (!defined('ABSPATH')) {
	exit;
}

final class KeySelector {

	/** @var Logger */
	private $logger;

	/** @var KeysRepo */
	private $repo;

	public function __construct() {
		$this->logger = Logger::instance();
		$this->repo   = new KeysRepo();
	}

	/**
	 * Select an available key using weighted random.
	 *
	 * @return SelectedKey|WP_Error
	 */
	public function select(string $provider) {
		$provider = sanitize_key($provider);

		if ($provider === '') {
			$this->logger->warning('keys.select.invalid_provider', ['provider' => $provider]);
			return new WP_Error('wpnexus_provider_invalid', 'Invalid provider.', ['provider' => $provider]);
		}

		$lock_name = 'keyselect:' . $provider;

		$locked = Lock::acquire($lock_name, 2);
		if (!$locked) {
			// Best-effort: still proceed but log.
			$this->logger->warning('keys.select.lock_busy', ['provider' => $provider]);
		}

		try {
			$active_count = $this->repo->count_active($provider);
			if ($active_count <= 0) {
				// IMPORTANT: normalize code so TranslateTask can mark job as needs_input.
				$this->logger->warning('keys.select.no_active_keys', ['provider' => $provider]);
				return new WP_Error('wpnexus_provider_no_keys', 'No active keys for provider.', ['provider' => $provider]);
			}

			$rows = $this->repo->list_available($provider, 300);

			if (empty($rows)) {
				// IMPORTANT: normalize code so TranslateTask can mark job as needs_input.
				// Provide best-effort hint about when a key may become available again.
				$retry_after = 0;
				$min_until_ts = 0;

				$all = $this->repo->list_by_provider($provider, 1000);
				$now = time();
				foreach ($all as $r) {
					$until = isset($r['cooldown_until']) ? (string) $r['cooldown_until'] : '';
					if ($until === '') {
						continue;
					}
					$ts = strtotime($until);
					if ($ts && $ts > $now) {
						if ($min_until_ts === 0 || $ts < $min_until_ts) {
							$min_until_ts = (int) $ts;
						}
					}
				}
				if ($min_until_ts > 0) {
					$retry_after = max(1, $min_until_ts - $now);
				}

				$this->logger->warning('keys.select.all_rate_limited', [
					'provider'     => $provider,
					'active_count' => $active_count,
					'retry_after'  => $retry_after,
				]);

				return new WP_Error(
					'wpnexus_provider_all_rate_limited',
					'All keys are rate-limited; wait or add more keys.',
					[
						'provider'    => $provider,
						'retry_after' => $retry_after,
					]
				);
			}

			$chosen = $this->weighted_pick($rows);
			if (!$chosen || empty($chosen['id'])) {
				return new WP_Error('wpnexus_key_pick_failed', 'Failed to select a key.', ['provider' => $provider]);
			}

			$id = (int) $chosen['id'];
			$plain = $this->repo->decrypt_key($chosen['key_cipher'] ?? null);
			if (!is_string($plain) || $plain === '') {
				$this->logger->error('keys.select.decrypt_failed', ['id' => $id, 'provider' => $provider]);
				return new WP_Error('wpnexus_key_decrypt_failed', 'Key decrypt failed.', ['provider' => $provider, 'id' => $id]);
			}

			$this->repo->mark_used($id);

			$this->logger->info('keys.select.ok', [
				'provider' => $provider,
				'id' => $id,
				'available' => count($rows),
				'active_count' => $active_count,
			]);

			return new SelectedKey($id, $provider, $plain);
		} finally {
			if ($locked) {
				Lock::release($lock_name);
			}
		}
	}

	/**
	 * Report outcomes (to be used by Providers / Jobs later).
	 */
	public function report_success(int $key_id): void {
		$this->logger->debug('keys.report.success', ['id' => $key_id]);
		$this->repo->record_success($key_id);
	}

	public function report_fail(int $key_id): void {
		$this->logger->debug('keys.report.fail', ['id' => $key_id]);
		$this->repo->record_fail($key_id);
	}

	/**
	 * Handle provider 429: apply cooldown with exponential backoff.
	 * Returns backoff seconds.
	 */
	public function report_429(int $key_id): int {
		$this->logger->warning('keys.report.429', ['id' => $key_id]);
		return $this->repo->record_429_and_cooldown($key_id);
	}

	/**
	 * Weighted random:
	 * - Prefer lower 429 count
	 * - Prefer lower usage (older last_used_at)
	 * - Prefer higher success relative to fail
	 *
	 * @param array<int, array<string,mixed>> $rows
	 * @return array<string,mixed>|null
	 */
	private function weighted_pick(array $rows): ?array {
		$weights = [];
		$total = 0.0;

		$now = time();

		foreach ($rows as $i => $r) {
			$success = (int) ($r['success_count'] ?? 0);
			$fail    = (int) ($r['fail_count'] ?? 0);
			$rl429   = (int) ($r['rate_429_count'] ?? 0);

			$last_used = 0;
			if (!empty($r['last_used_at'])) {
				$ts = strtotime((string) $r['last_used_at']);
				$last_used = $ts ? (int) $ts : 0;
			}

			// Base weight
			$w = 1.0;

			// Penalize heavy 429 history
			$w *= 1.0 / (1.0 + min(50, $rl429));

			// Prefer keys not used recently (0..1.5 multiplier)
			$age = ($last_used > 0) ? max(0, $now - $last_used) : 86400;
			$w *= 1.0 + min(0.5, $age / 86400.0); // up to +0.5

			// Success ratio boost (0.8..1.4)
			$den = max(1, $success + $fail);
			$ratio = $success / $den;
			$w *= 0.8 + (0.6 * $ratio);

			// Guard
			$w = max(0.0001, $w);

			$weights[$i] = $w;
			$total += $w;
		}

		if ($total <= 0.0) {
			return $rows[array_key_first($rows)] ?? null;
		}

		$rand = lcg_value() * $total;
		$acc = 0.0;

		foreach ($rows as $i => $r) {
			$acc += $weights[$i] ?? 0.0;
			if ($rand <= $acc) {
				return $r;
			}
		}

		return $rows[array_key_last($rows)] ?? null;
	}
}
