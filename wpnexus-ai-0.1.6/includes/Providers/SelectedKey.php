<?php
namespace WPNexusAI\Providers;

if (!defined('ABSPATH')) {
	exit;
}

final class SelectedKey {
	/** @var int */
	public $id;

	/** @var string */
	public $provider;

	/** @var string */
	public $key;

	public function __construct(int $id, string $provider, string $key) {
		$this->id = $id;
		$this->provider = $provider;
		$this->key = $key;
	}
}
