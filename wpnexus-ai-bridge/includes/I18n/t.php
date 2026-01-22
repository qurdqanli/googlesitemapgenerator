<?php
if (!defined('ABSPATH')) {
	exit;
}

function t(string $key, array $args = []): string {
	static $catalog = null;

	if ($catalog === null) {
		$path = defined('WPNEXUS_AI_BRIDGE_DIR') ? WPNEXUS_AI_BRIDGE_DIR . 'languages/en.php' : null;
		$catalog = is_string($path) && is_readable($path) ? require $path : [];
	}

	$raw = $catalog[$key] ?? null;

	if (!is_string($raw) || $raw === '') {
		$raw = '[' . $key . ']';
	}

	$translated = __($raw, 'wpnexus-ai-bridge');

	if (!empty($args)) {
		return vsprintf($translated, $args);
	}

	return $translated;
}
