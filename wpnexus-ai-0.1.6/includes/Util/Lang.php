<?php
namespace WPNexusAI\Util;

if (!defined('ABSPATH')) {
	exit;
}

final class Lang {

	/**
	 * Convert WP locale (en_US) to a normalized language code.
	 * If it can't be parsed, returns 'auto'.
	 */
	public static function from_locale(string $locale): string {
		$locale = trim((string) $locale);
		if ($locale === '') {
			return 'auto';
		}

		// WP locales are often like en_US, az_AZ, ru_RU.
		// Normalize will handle both locale and plain codes.
		return self::normalize($locale);
	}

	/**
	 * Normalize various language inputs to a stable language code.
	 *
	 * Examples:
	 * - "en_US" -> "en"
	 * - "en-US" -> "en"
	 * - "AZ"    -> "az"
	 * - "zh_Hans" -> "zh-hans" (keep script)
	 * - "" -> "auto"
	 */
	public static function normalize(string $code): string {
		$code = trim((string) $code);
		if ($code === '') {
			return 'auto';
		}

		$code = strtolower($code);

		if ($code === 'auto') {
			return 'auto';
		}

		// Replace underscores with hyphens for consistency (en_US -> en-us)
		$code = str_replace('_', '-', $code);

		$parts = array_values(array_filter(explode('-', $code), function ($p) {
			return $p !== '';
		}));

		if (empty($parts)) {
			return 'auto';
		}

		$primary = self::sanitize_code($parts[0]);
		if ($primary === '') {
			return 'auto';
		}

		// Keep common script variants for zh (zh-hans / zh-hant)
		if ($primary === 'zh' && isset($parts[1])) {
			$second = self::sanitize_code($parts[1]);
			if (in_array($second, ['hans', 'hant'], true)) {
				return 'zh-' . $second;
			}
		}

		// Default: return primary language only (en-us -> en)
		return $primary;
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

