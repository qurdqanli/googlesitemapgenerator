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

final class ClaudeAdapter implements ProviderInterface {

	/** @var Logger */
	private $logger;

	/** @var JsonHttpClient */
	private $http;

	public function __construct() {
		$this->logger = Logger::instance();
		$this->http   = new JsonHttpClient();
	}

	public function id(): string {
		return 'claude';
	}

	public function translate(TranslateRequest $req, SelectedKey $key) {
		$endpoint = (string) apply_filters('wpnexus_ai_claude_endpoint', 'https://api.anthropic.com/v1/messages', $req);
		$model    = (string) apply_filters('wpnexus_ai_claude_model', 'claude-3-5-sonnet-20240620', $req);

		$system = (string) apply_filters('wpnexus_ai_claude_system_prompt',
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
			'max_tokens' => (int) apply_filters('wpnexus_ai_claude_max_tokens', 2048, $req),
			'temperature' => 0,
			'system' => $system,
			'messages' => [
				['role' => 'user', 'content' => wp_json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
			],
		];

		$body = apply_filters('wpnexus_ai_claude_body', $body, $req);

		$headers = [
			'x-api-key' => $key->key,
			'anthropic-version' => (string) apply_filters('wpnexus_ai_claude_version', '2023-06-01', $req),
		];

		$res = $this->http->post_json($endpoint, $headers, $body, 45);
		if (is_wp_error($res)) {
			return $res;
		}

		$status = (int) $res['status'];
		if ($status === 429) {
			return new WP_Error('wpnexus_provider_429', 'Claude rate limited.');
		}

		if ($status < 200 || $status >= 300) {
			$this->logger->warning('providers.claude.http_error', [
				'status' => $status,
			]);
			return new WP_Error('wpnexus_provider_http_error', t('provider_err_api'), ['status' => $status]);
		}

		$json = $res['json'];
		if (!is_array($json)) {
			return new WP_Error('wpnexus_provider_bad_json', t('provider_err_bad_response'));
		}

		$text = '';
		// Typical Anthropic response: content[0].text
		if (isset($json['content'][0]['text'])) {
			$text = (string) $json['content'][0]['text'];
		}

		$parsed = $this->extract_json_object($text);
		if (!is_array($parsed)) {
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

		return new TranslateResult('claude', (int) $key->id, $translations, $usage);
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
