<?php
namespace WPNexusAIBridge\Domain\Services;

if (!defined('ABSPATH')) {
	exit;
}

final class SiteService {

	public function get_site_info(): array {
		$langs = new LanguagesService();

		$timezone = '';
		if (function_exists('wp_timezone_string')) {
			$timezone = (string) wp_timezone_string();
		}
		if ($timezone === '') {
			$timezone = (string) get_option('timezone_string', '');
		}
		if ($timezone === '') {
			$timezone = 'UTC' . (string) get_option('gmt_offset', '0');
		}

		return [
			'locale'           => determine_locale(),
			'timezone'         => $timezone,
			'admin_language'   => function_exists('get_user_locale') ? get_user_locale() : determine_locale(),
			'default_language' => $langs->default_language(),
		];
	}
}
