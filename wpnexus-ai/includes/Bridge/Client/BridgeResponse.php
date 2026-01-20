<?php
namespace WPNexusAI\Bridge\Client;

if (!defined('ABSPATH')) {
	exit;
}

final class BridgeResponse {

	/** @var bool */
	public $ok = false;

	/** @var int|null */
	public $status = null;

	/** @var string|null */
	public $error = null;

	/** @var array<string,mixed>|null */
	public $json = null;

	/** @var string|null */
	public $raw = null;

	/** @var array<string,string|string[]> */
	public $headers = [];

	/**
	 * @param array<string,mixed> $args
	 */
	public static function from(array $args): BridgeResponse {
		$r = new self();
		foreach ($args as $k => $v) {
			if (property_exists($r, (string) $k)) {
				$r->{$k} = $v;
			}
		}
		return $r;
	}
}
