<?php
namespace WPNexusAI\Providers;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Translation result DTO returned by provider adapters.
 *
 * Adapters currently construct it as:
 *   new TranslateResult('openai', (int)$key->id, $translations, $usage);
 *
 * Where $translations is array<string,string> keyed by segment key (title/excerpt/content).
 */
final class TranslateResult {

	/** @var string */
	public $provider;

	/** @var int */
	public $key_id;

	/** @var array<string,string> */
	public $translations = [];

	/** @var array<string,mixed> */
	public $usage = [];

	/** @var array<string,mixed> */
	public $meta = [];

	/**
	 * @param string $provider
	 * @param int $key_id
	 * @param array<string,string> $translations
	 * @param array<string,mixed> $usage
	 * @param array<string,mixed> $meta
	 */
	public function __construct(string $provider, int $key_id, array $translations, array $usage = [], array $meta = []) {
		$this->provider     = sanitize_key($provider);
		$this->key_id       = (int) $key_id;
		$this->translations = $this->sanitize_translations($translations);
		$this->usage        = is_array($usage) ? $usage : [];
		$this->meta         = is_array($meta) ? $meta : [];
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
			'meta'         => $this->meta,
		];
	}

	/**
	 * @param array<string,mixed> $arr
	 */
	public static function from_array(array $arr): self {
		$provider = isset($arr['provider']) ? (string) $arr['provider'] : '';
		$key_id   = isset($arr['key_id']) ? (int) $arr['key_id'] : 0;

		$translations = [];
		if (isset($arr['translations']) && is_array($arr['translations'])) {
			$translations = $arr['translations'];
		}

		$usage = [];
		if (isset($arr['usage']) && is_array($arr['usage'])) {
			$usage = $arr['usage'];
		}

		$meta = [];
		if (isset($arr['meta']) && is_array($arr['meta'])) {
			$meta = $arr['meta'];
		}

		return new self($provider, $key_id, $translations, $usage, $meta);
	}

	/**
	 * @param array<string,mixed> $translations
	 * @return array<string,string>
	 */
	private function sanitize_translations(array $translations): array {
		$out = [];

		foreach ($translations as $k => $v) {
			$key = sanitize_key((string) $k);
			if ($key === '') {
				continue;
			}

			if (is_scalar($v) || (is_object($v) && method_exists($v, '__toString'))) {
				$out[$key] = (string) $v;
			} else {
				$out[$key] = '';
			}
		}

		return $out;
	}
}

