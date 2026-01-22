<?php
namespace WPNexusAI\Providers;

use WP_Error;

if (!defined('ABSPATH')) {
	exit;
}

interface ProviderInterface {

	/**
	 * Provider slug (openai|claude|gemini|custom)
	 */
	public function id(): string;

	/**
	 * @return TranslateResult|WP_Error
	 */
	public function translate(TranslateRequest $req, SelectedKey $key);
}
