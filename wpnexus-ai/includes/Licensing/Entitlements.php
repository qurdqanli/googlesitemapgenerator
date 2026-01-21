<?php
namespace WPNexusAI\Licensing;

if (!defined('ABSPATH')) {
	exit;
}

final class Entitlements {

	/** @var int */
	public $targets_limit = 0; // 0 = unlimited (testing)

	/** @var array<string,bool> */
	public $features = [];

	public static function default_unlicensed(): self {
		$e = new self();
		$e->targets_limit = 0; // IMPORTANT: allow full testing
		$e->features = [
			'bridge' => true,
			'woo'    => true,
			'seo'    => true,
		];
		return $e;
	}

	/**
	 * @param mixed $json
	 */
	public static function from_json($json): self {
		$e = new self();
		$arr = null;

		if (is_string($json) && $json !== '') {
			$dec = json_decode($json, true);
			if (is_array($dec)) {
				$arr = $dec;
			}
		} elseif (is_array($json)) {
			$arr = $json;
		}

		if (!is_array($arr)) {
			return self::default_unlicensed();
		}

		$e->targets_limit = isset($arr['targets_limit']) ? (int) $arr['targets_limit'] : 0;
		$e->features = isset($arr['features']) && is_array($arr['features']) ? $arr['features'] : [];

		return $e;
	}
}
