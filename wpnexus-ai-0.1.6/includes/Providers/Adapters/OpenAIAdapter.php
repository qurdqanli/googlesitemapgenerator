<?php
namespace WPNexusAI\Providers\Adapters;

use WP_Error;
use WPNexusAI\Providers\ProviderInterface;
use WPNexusAI\Providers\SelectedKey;
use WPNexusAI\Providers\TranslateRequest;
use WPNexusAI\Providers\TranslateResult;
use WPNexusAI\Providers\Http\JsonHttpClient;
use WPNexusAI\Logging\Logger;

if (!defined('ABSPATH')) {
	exit;
}

final class OpenAIAdapter implements ProviderInterface {

	/** @var Logger */
	private $logger;

	/** @var JsonHttpClient */
	private $http;

	public function __construct() {
		$this->logger = Logger::instance();
		$this->http   = new JsonHttpClient();
	}

	public function id(): string {
		return 'openai';
	}

	public function translate(TranslateRequest $req, SelectedKey $key) {
		$endpoint = (string) apply_filters('wpnexus_ai_openai_endpoint', 'https://api.openai.com/v1/chat/completions', $req);
		$model    = (string) apply_filters('wpnexus_ai_openai_model', 'gpt-4o-mini', $req);

		$system = (string) apply_filters('wpnexus_ai_openai_system_prompt',
			'You are a professional translation engine. Translate text segments faithfully. Preserve HTML, shortcodes, and placeholders exactly. Return ONLY valid JSON object mapping segment keys to translated strings.',
			$req
		);

		$segments = [];
		foreach ($req->segments as $seg) {
			$k = isset($seg['key']) ? (string) $seg['key'] : '';
			$t = isset($seg['text']) ? (string) $seg['text'] : '';
			if ($k !== '') {
				$segments[$k] = $t;
			}
		}

		$user = [
			'from'     => $req->source_lang,
			'to'       => $req->target_lang,
			'segments' => $segments,
		];

		$body = [
			'model' => $model,
			'temperature' => 0,
			'messages' => [
				['role' => 'system', 'content' => $system],
				['role' => 'user', 'content' => wp_json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
			],
		];

		// Best-effort JSON mode (some models support it; harmless if ignored).
		$body = apply_filters('wpnexus_ai_openai_body', $body, $req);

		$headers = [
			'Authorization' => 'Bearer ' . $key->key,
		];

		$res = $this->http->post_json($endpoint, $headers, $body, 45);
		if (is_wp_error($res)) {
			return $res;
		}

		$status = (int) $res['status'];
		if ($status === 429) {
			return new WP_Error('wpnexus_provider_429', 'OpenAI rate limited.');
		}

		if ($status < 200 || $status >= 300) {
			$this->logger->warning('providers.openai.http_error', [
				'status' => $status,
			]);
			return new WP_Error('wpnexus_provider_http_error', t('provider_err_api'), ['status' => $status]);
		}

		$json = $res['json'];
		if (!is_array($json)) {
			return new WP_Error('wpnexus_provider_bad_json', t('provider_err_bad_response'));
		}

		$content = '';
		if (isset($json['choices'][0]['message']['content'])) {
			$content = (string) $json['choices'][0]['message']['content'];
		}

		$parsed = $this->extract_json_object($content);
		if (!is_array($parsed)) {
			$this->logger->warning('providers.openai.parse_fail', [
				'len' => strlen($content),
			]);
			return new WP_Error('wpnexus_provider_parse_failed', t('provider_err_bad_response'));
		}

		$translations = [];
		foreach ($segments as $k => $_) {
			$translations[$k] = isset($parsed[$k]) ? (string) $parsed[$k] : '';
		}

		$usage = [];
		if (isset($json['usage']) && is_array($json['usage'])) {
			$usage = $json['usage'];
		}

		return new TranslateResult('openai', (int) $key->id, $translations, $usage);
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function extract_json_object(string $text): ?array {
		$text = trim($text);
		$dec = json_decode($text, true);
		if (is_array($dec)) {
			return $dec;
		}

		// Try to salvage: take substring between first { and last }
		$start = strpos($text, '{');
		$end   = strrpos($text, '}');
		if ($start === false || $end === false || $end <= $start) {
			return null;
		}

		$sub = substr($text, $start, ($end - $start) + 1);
		$dec = json_decode($sub, true);
		return is_array($dec) ? $dec : null;
	}
}
