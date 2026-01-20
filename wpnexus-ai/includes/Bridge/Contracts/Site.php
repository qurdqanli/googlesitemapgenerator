<?php
namespace WPNexusAI\Bridge\Contracts;

if (!defined('ABSPATH')) {
	exit;
}

final class Site {

	/** @var string|null */
	public $locale;

	/** @var string|null */
	public $timezone;

	/** @var string|null */
	public $admin_language;

	/** @var string|null */
	public $default_language;

	/**
	 * @param array<string,mixed> $data
	 */
	public static function from_array(array $data): Site {
		$s = new self();

		$s->locale = isset($data['locale']) ? (string) $data['locale'] : null;
		$s->timezone = isset($data['timezone']) ? (string) $data['timezone'] : null;
		$s->admin_language = isset($data['admin_language']) ? (string) $data['admin_language'] : null;
		$s->default_language = isset($data['default_language']) ? (string) $data['default_language'] : null;

		return $s;
	}
}
