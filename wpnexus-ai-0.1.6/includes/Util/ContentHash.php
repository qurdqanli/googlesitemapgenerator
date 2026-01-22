<?php
namespace WPNexusAI\Util;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Stable hashing for idempotent sync.
 *
 * Produces a SHA-256 hash from a canonical JSON representation.
 */
final class ContentHash {

	/**
	 * @param mixed $data
	 */
	public static function hash($data): string {
		$canonical = self::canonicalize($data);
		$json = wp_json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if (!is_string($json)) {
			$json = '';
		}

		return hash('sha256', $json);
	}

	/**
	 * @param mixed $data
	 * @return mixed
	 */
	private static function canonicalize($data) {
		if (is_array($data)) {
			$is_assoc = array_keys($data) !== range(0, count($data) - 1);

			if ($is_assoc) {
				ksort($data);
			}

			$out = [];
			foreach ($data as $k => $v) {
				$out[$k] = self::canonicalize($v);
			}

			return $out;
		}

		if (is_object($data)) {
			$vars = get_object_vars($data);
			ksort($vars);

			$out = [];
			foreach ($vars as $k => $v) {
				$out[$k] = self::canonicalize($v);
			}
			return $out;
		}

		if (is_resource($data)) {
			return null;
		}

		return $data;
	}
}
