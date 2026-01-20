<?php
namespace WPNexusAI\Bridge\Contracts;

if (!defined('ABSPATH')) {
	exit;
}

final class Health {

	/** @var string|null */
	public $product;

	/** @var string|null */
	public $version;

	/** @var bool|null */
	public $multisite;

	/** @var array<string,mixed> */
	public $integrations = [];

	/**
	 * @param array<string,mixed> $data
	 */
	public static function from_array(array $data): Health {
		$h = new self();

		$h->product = isset($data['product']) ? (string) $data['product'] : (isset($data['name']) ? (string) $data['name'] : null);
		$h->version = isset($data['version']) ? (string) $data['version'] : null;

		if (isset($data['multisite'])) {
			$h->multisite = (bool) $data['multisite'];
		} elseif (isset($data['is_multisite'])) {
			$h->multisite = (bool) $data['is_multisite'];
		}

		if (isset($data['integrations']) && is_array($data['integrations'])) {
			$h->integrations = $data['integrations'];
		}

		return $h;
	}
}
