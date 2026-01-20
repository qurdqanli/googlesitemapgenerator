<?php
namespace WPNexusAI\Util;

use WPNexusAI\Logging\Logger;

if (!defined('ABSPATH')) {
	exit;
}

final class Lock {

	/**
	 * Best-effort lock using object cache or transient.
	 */
	public static function acquire(string $name, int $ttl_seconds = 2): bool {
		$logger = Logger::instance();
		$key = 'wpnexus_ai_lock_' . md5($name);
		$ttl_seconds = max(1, min(30, $ttl_seconds));

		// Object cache (atomic if persistent cache)
		if (function_exists('wp_cache_add')) {
			$ok = wp_cache_add($key, 1, 'wpnexus_ai', $ttl_seconds);
			if ($ok) {
				return true;
			}
		}

		// Fallback transient (not fully atomic, best-effort)
		$existing = get_transient($key);
		if ($existing) {
			$logger->debug('lock.busy', ['name' => $name]);
			return false;
		}

		set_transient($key, 1, $ttl_seconds);
		return true;
	}

	public static function release(string $name): void {
		$key = 'wpnexus_ai_lock_' . md5($name);

		if (function_exists('wp_cache_delete')) {
			wp_cache_delete($key, 'wpnexus_ai');
		}

		delete_transient($key);
	}
}
