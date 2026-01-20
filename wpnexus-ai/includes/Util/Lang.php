<?php
namespace WPNexusAI\Util;

if (!defined('ABSPATH')) {
	exit;
}

final class Lang {

	/**
	 * Convert WP locale (en_US) to a normalized language code (en).
	 * If it can't be parsed, returns 'auto'.
	 */
	public static function from_locale(string $locale): string {
		$locale = trim((string) $locale);
		if ($locale === '') {
			return 'auto';
		}

		// en_US -> en
		if (strpos($locale, '_') !== false) {
			$parts = explode('_', $locale);
			$base = strtolower((string) ($parts[0] ?? ''));
			return $base !== '' ? self::sanitize_code($base) : 'auto';
		}

		// en-US -> en
		if (strpos($locale, '-') !== false) {
			$parts = explode('-', $locale);
			$base = strtolower((string) ($parts[0] ?? ''));
			return $base !== '' ? self::sanitize_code($base) : 'auto';
		}

		return self::sanitize_code(strtolower($locale)) ?: 'auto';
	}

	/**
	 * Keep only [a-z0-9_-] and lowercase.
	 */
	public static function sanitize_code(string $code): string {
		$code = strtolower(trim((string) $code));
		$code = preg_replace('/[^a-z0-9_\-]/', '', $code);
		return is_string($code) ? $code : '';
	}
}
