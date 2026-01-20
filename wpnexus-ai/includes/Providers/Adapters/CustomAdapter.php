<?php
namespace WPNexusAI\Providers\Adapters;

use WP_Error;
use WPNexusAI\Providers\ProviderInterface;
use WPNexusAI\Providers\SelectedKey;
use WPNexusAI\Providers\TranslateRequest;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Placeholder for future "custom" provider.
 * (Later: configure endpoint + auth scheme in Settings and call it here.)
 */
final class CustomAdapter implements ProviderInterface {

	public function id(): string {
		return 'custom';
	}

	public function translate(TranslateRequest $req, SelectedKey $key) {
		return new WP_Error('wpnexus_custom_provider_not_configured', 'Custom provider is not configured yet.');
	}
}
