<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * UI helper: always use t('key') in UI.
 * Canonical strings live in languages/en.php.
 *
 * @param string $key
 * @param array  $args sprintf-like args (optional)
 * @return string
 */
function t(string $key, array $args = []): string {
	static $catalog = null;

	if ($catalog === null) {
		$path = defined('WPNEXUS_AI_DIR') ? WPNEXUS_AI_DIR . 'languages/en.php' : null;
		$catalog = is_string($path) && is_readable($path) ? require $path : [];
	}

	$raw = $catalog[$key] ?? null;

	if (!is_string($raw) || $raw === '') {
		// If missing key, return a visible placeholder (helps QA).
		$raw = '[' . $key . ']';
	}

	$translated = __($raw, 'wpnexus-ai');

	if (!empty($args)) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return vsprintf($translated, $args);
	}

	return $translated;
}
