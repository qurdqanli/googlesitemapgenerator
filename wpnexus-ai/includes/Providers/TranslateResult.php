<?php
namespace WPNexusAI\Providers;

if (!defined('ABSPATH')) {
	exit;
}

final class TranslateResult {

	/** @var string */
	public $provider;

	/** @var int */
	public $key_id;

	/** @var array<string,string> */
	public $translations;

	/** @var array<string,mixed> */
	public $usage;

	/**
	 * @param array<string,string> $translations
	 * @param array<string,mixed> $usage
	 */
	public function __construct(string $provider, int $key_id, array $translations, array $usage = []) {
		$this->provider     = sanitize_key($provider);
		$this->key_id       = (int) $key_id;
		$this->translations = $translations;
		$this->usage        = $usage;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return [
			'provider'     => $this->provider,
			'key_id'       => $this->key_id,
			'translations' => $this->translations,
			'usage'        => $this->usage,
		];
	}
}
