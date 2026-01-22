<?php
namespace WPNexusAI\Providers;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Immutable-ish translation request DTO.
 *
 * Supports two constructor styles (back-compat):
 * 1) new TranslateRequest([ 'source_lang' => 'az', 'target_lang' => 'en', 'segments' => [...], ... ])
 * 2) new TranslateRequest('az', 'en', $segments, $context, $opts)
 */
final class TranslateRequest {

	/** @var string */
	public $source_lang = 'auto';

	/** @var string */
	public $target_lang = '';

	/**
	 * @var array<int,array<string,mixed>>
	 * Each segment should minimally include: ['key' => 'title|excerpt|content|...', 'text' => '...']
	 */
	public $segments = [];

	/** @var array<string,mixed> */
	public $context = [];

	/** @var string Preferred provider (optional) */
	public $provider = '';

	/** @var string Preferred model (optional) */
	public $model = '';

	/** @var string Optional tone/style hint */
	public $tone = '';

	/** @var array<string,string> Optional glossary (source=>target) */
	public $glossary = [];

	/**
	 * @param mixed $source_or_args
	 * @param mixed $target_lang
	 * @param mixed $segments
	 * @param mixed $context
	 * @param mixed $opts
	 */
	public function __construct($source_or_args, $target_lang = '', $segments = [], $context = [], $opts = []) {
		$args = [];

		// Style (1): associative args array.
		if (is_array($source_or_args)) {
			$args = $source_or_args;
		} else {
			// Style (2): positional.
			$args = [
				'source_lang' => (string) $source_or_args,
				'target_lang' => (string) $target_lang,
				'segments'    => is_array($segments) ? $segments : [],
				'context'     => is_array($context) ? $context : [],
			];

			if (is_array($opts) && !empty($opts)) {
				$args = array_merge($args, $opts);
			}
		}

		$source = isset($args['source_lang']) ? (string) $args['source_lang'] : 'auto';
		$target = isset($args['target_lang']) ? (string) $args['target_lang'] : '';

		$source = trim($source);
		$target = trim($target);

		$this->source_lang = ($source === '' ? 'auto' : $source);
		$this->target_lang = $target;

		$segments_in = isset($args['segments']) && is_array($args['segments']) ? $args['segments'] : [];
		$this->segments = $this->normalize_segments($segments_in);

		$this->context = isset($args['context']) && is_array($args['context']) ? $args['context'] : [];

		$this->provider = isset($args['provider']) ? sanitize_key((string) $args['provider']) : '';
		$this->model    = isset($args['model']) ? sanitize_text_field((string) $args['model']) : '';
		$this->tone     = isset($args['tone']) ? sanitize_text_field((string) $args['tone']) : '';

		$this->glossary = [];
		if (isset($args['glossary']) && is_array($args['glossary'])) {
			foreach ($args['glossary'] as $k => $v) {
				$kk = sanitize_text_field((string) $k);
				$vv = sanitize_text_field((string) $v);
				if ($kk !== '' && $vv !== '') {
					$this->glossary[$kk] = $vv;
				}
			}
		}
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
			'provider'    => $this->provider,
			'model'       => $this->model,
			'tone'        => $this->tone,
			'glossary'    => $this->glossary,
		];
	}

	/**
	 * @param array<int,mixed> $segments
	 * @return array<int,array<string,mixed>>
	 */
	private function normalize_segments(array $segments): array {
		$out = [];

		foreach ($segments as $seg) {
			if (!is_array($seg)) {
				continue;
			}

			$key  = isset($seg['key']) ? (string) $seg['key'] : '';
			$text = isset($seg['text']) ? (string) $seg['text'] : '';

			$key  = sanitize_key($key);
			$text = (string) $text;

			if ($key === '' || $text === '') {
				continue;
			}

			$row = [
				'key'  => $key,
				'text' => $text,
			];

			// Preserve optional fields if present (e.g., html flag, hints, etc.)
			foreach (['is_html', 'hint'] as $opt) {
				if (isset($seg[$opt])) {
					$row[$opt] = $seg[$opt];
				}
			}

			$out[] = $row;
		}

		return $out;
	}
}

