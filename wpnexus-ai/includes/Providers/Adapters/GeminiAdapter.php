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

final class GeminiAdapter implements ProviderInterface {

	/** @var Logger */
	private $logger;

	/** @var JsonHttpClient */
	private $http;

	public function __construct() {
		$this->logger = Logger::instance();
		$this->http   = new JsonHttpClient();
	}

	public function id(): string {
		return 'gemini';
	}

	public function translate(TranslateRequest $req, SelectedKey $key) {
		$model = (string) apply_filters('wpnexus_ai_gemini_model', 'gemini-1.5-flash', $req);
		$base  = (string) apply_filters('wpnexus_ai_gemini_base', 'https://generativelanguage.googleapis.com/v1beta/models/', $req);

		$endpoint = rtrim($base, '/') . '/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($key->key);

		$system = (string) apply_filters('wpnexus_ai_gemini_system_prompt',
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

		$prompt = $system . "\n\n" . wp_json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		$body = [
			'contents' => [
				['parts' => [['text' => $prompt]]],
			],
			'generationConfig' => [
				'temperature' => 0,
			],
		];

		$body = apply_filters('wpnexus_ai_gemini_body', $body, $req);

		$res = $this->http->post_json($endpoint, [], $body, 45);
		if (is_wp_error($res)) {
			return $res;
		}

		$status = (int) $res['status'];
		if ($status === 429) {
			return new WP_Error('wpnexus_provider_429', 'Gemini rate limited.');
		}

		if ($status < 200 || $status >= 300) {
			$this->logger->warning('providers.gemini.http_error', [
				'status' => $status,
			]);
			return new WP_Error('wpnexus_provider_http_error', t('provider_err_api'), ['status' => $status]);
		}

		$json = $res['json'];
		if (!is_array($json)) {
			return new WP_Error('wpnexus_provider_bad_json', t('provider_err_bad_response'));
		}

		$text = '';
		if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
			$text = (string) $json['candidates'][0]['content']['parts'][0]['text'];
		}

		$parsed = $this->extract_json_object($text);
		if (!is_array($parsed)) {
			return new WP_Error('wpnexus_provider_parse_failed', t('provider_err_bad_response'));
		}

		$translations = [];
		foreach ($segments as $k => $_) {
			$translations[$k] = isset($parsed[$k]) ? (string) $parsed[$k] : '';
		}

		return new TranslateResult('gemini', (int) $key->id, $translations, []);
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
