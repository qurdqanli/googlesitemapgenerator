<?php
namespace WPNexusAI\Providers;

if (!defined('ABSPATH')) {
	exit;
}

final class TranslateRequest {

	/** @var string */
	public $source_lang;

	/** @var string */
	public $target_lang;

	/** @var array<int,array{key:string,text:string}> */
	public $segments;

	/** @var array<string,mixed> */
	public $context;

	/**
	 * @param array<int,array{key:string,text:string}> $segments
	 * @param array<string,mixed> $context
	 */
	public function __construct(string $source_lang, string $target_lang, array $segments, array $context = []) {
		$this->source_lang = sanitize_key($source_lang);
		$this->target_lang = sanitize_key($target_lang);
		$this->segments    = $segments;
		$this->context     = $context;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return [
			'source_lang' => $this->source_lang,
			'target_lang' => $this->target_lang,
			'segments'    => $this->segments,
			'context'     => $this->context,
		];
	}
}
