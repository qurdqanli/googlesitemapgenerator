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
		// Hard defaults (can be overridden by wp_options and/or filters).
		$opt_model = get_option('wpnexus_ai_gemini_model');
		$opt_base  = get_option('wpnexus_ai_gemini_base');

		$default_model = (is_string($opt_model) && $opt_model !== '') ? $opt_model : 'gemini-1.5-flash-latest';
		$default_base  = (is_string($opt_base) && $opt_base !== '') ? $opt_base : 'https://generativelanguage.googleapis.com/v1/models/';

		$model = (string) apply_filters('wpnexus_ai_gemini_model', $default_model, $req);
		$base  = (string) apply_filters('wpnexus_ai_gemini_base', $default_base, $req);

		$model_candidates = $this->build_model_candidates($model);
		$base_candidates  = $this->build_base_candidates($base);

		/** Allow integrators to fully control candidate lists (explicit models). */
		$model_candidates = (array) apply_filters('wpnexus_ai_gemini_model_candidates', $model_candidates, $req);
		$base_candidates  = (array) apply_filters('wpnexus_ai_gemini_base_candidates', $base_candidates, $req);

		$system = (string) apply_filters(
			'wpnexus_ai_gemini_system_prompt',
			'You are a professional translation engine. Translate text segments faithfully. Return ONLY a valid JSON object mapping segment keys to translated strings.',
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

		$last_error = null;

		/**
		 * Try (base x model) combinations.
		 * NOTE: We prefer header-based key to avoid leaking API keys into URLs.
		 * If an environment requires query-key, we retry once with query on auth errors.
		 */
		foreach ($base_candidates as $base_try) {
			$base_try = is_string($base_try) ? trim($base_try) : '';
			if ($base_try === '') {
				continue;
			}

			foreach ($model_candidates as $model_try) {
				$model_try = is_string($model_try) ? trim($model_try) : '';
				if ($model_try === '') {
					continue;
				}

				$endpoint = $this->build_endpoint($base_try, $model_try, false, $key->key);
				$headers  = [
					'x-goog-api-key' => $key->key,
				];

				$res = $this->http->post_json($endpoint, $headers, $body, 45);
				if (is_wp_error($res)) {
					// Network/transport error is not recoverable by switching models.
					return $res;
				}

				$status = (int) $res['status'];

				// Retry auth failure once with query key (some setups might require it).
				if (($status === 401 || $status === 403) && strpos($endpoint, '?key=') === false) {
					$endpoint_q = $this->build_endpoint($base_try, $model_try, true, $key->key);
					$res_q      = $this->http->post_json($endpoint_q, [], $body, 45);
					if (!is_wp_error($res_q)) {
						$res    = $res_q;
						$status = (int) $res['status'];
					}
				}

				if ($status === 429) {
					return new WP_Error('wpnexus_provider_429', 'Gemini rate limited.');
				}

				if ($status === 404) {
					// Keep trying other model/base combinations.
					$last_error = new WP_Error(
						'wpnexus_provider_model_not_found',
						t('provider_err_model_not_found'),
						[
							'status' => 404,
							'model'  => $model_try,
							'base'   => $base_try,
						]
					);
					$this->logger->warning('providers.gemini.model_not_found', [
						'status' => 404,
						'model'  => $model_try,
						'base'   => $base_try,
					]);
					continue;
				}

				if ($status < 200 || $status >= 300) {
					$msg = $this->extract_error_message($res);
					$this->logger->warning('providers.gemini.http_error', [
						'status' => $status,
						'model'  => $model_try,
						'base'   => $base_try,
						'msg'    => $msg,
					]);

					return new WP_Error(
						'wpnexus_provider_http_error',
						t('provider_err_api'),
						[
							'status' => $status,
							'model'  => $model_try,
							'base'   => $base_try,
							'msg'    => $msg,
						]
					);
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

				return new TranslateResult('gemini', (int) $key->id, $translations, [
					'model' => $model_try,
					'base'  => $base_try,
				]);
			}
		}

		return $last_error ? $last_error : new WP_Error('wpnexus_provider_model_not_found', t('provider_err_model_not_found'));
	}

	/**
	 * Build endpoint URL.
	 *
	 * @param string $base Base URL, e.g. https://generativelanguage.googleapis.com/v1/models/
	 * @param string $model Model name, e.g. gemini-1.5-flash-latest
	 * @param bool   $with_query_key Add ?key=... query param
	 * @param string $api_key API key
	 * @return string
	 */
	private function build_endpoint(string $base, string $model, bool $with_query_key, string $api_key): string {
		$base = trim($base);

		// If integrator provides just the host, normalize.
		if (strpos($base, 'generativelanguage.googleapis.com') !== false && strpos($base, '/models') === false) {
			$base = rtrim($base, '/') . '/v1/models/';
		}

		$endpoint = rtrim($base, '/') . '/' . rawurlencode($model) . ':generateContent';

		if ($with_query_key) {
			$endpoint .= '?key=' . rawurlencode($api_key);
		}

		return $endpoint;
	}

	/**
	 * @param string $model
	 * @return array<int,string>
	 */
	private function build_model_candidates(string $model): array {
		$model = trim($model);
		$out   = [];

		if ($model !== '') {
			$out[] = $model;
		}

		// Common "latest" suffix variants.
		if ($model !== '' && substr($model, -7) !== '-latest') {
			$out[] = $model . '-latest';
		}

		// If someone still uses an old default, push a safer one.
		if ($model === 'gemini-1.5-flash') {
			$out[] = 'gemini-1.5-flash-latest';
		}

		// De-duplicate.
		$out = array_values(array_unique(array_filter($out)));

		return $out;
	}

	/**
	 * @param string $base
	 * @return array<int,string>
	 */
	private function build_base_candidates(string $base): array {
		$base = trim($base);
		$out  = [];

		if ($base !== '') {
			$out[] = $base;
		}

		// Swap v1beta <-> v1 if present.
		if (strpos($base, '/v1beta/') !== false) {
			$out[] = str_replace('/v1beta/', '/v1/', $base);
		}
		if (strpos($base, '/v1/') !== false) {
			$out[] = str_replace('/v1/', '/v1beta/', $base);
		}

		// Ensure /models/ exists.
		foreach ($out as $b) {
			if (strpos($b, '/models') === false && strpos($b, 'generativelanguage.googleapis.com') !== false) {
				$out[] = rtrim($b, '/') . '/models/';
			}
		}

		// De-duplicate.
		$out = array_values(array_unique(array_filter($out)));

		return $out;
	}

	/**
	 * @param array<string,mixed> $res
	 */
	private function extract_error_message(array $res): string {
		$payload = isset($res['json']) ? $res['json'] : null;
		if (is_array($payload)) {
			if (isset($payload['error']['message']) && is_string($payload['error']['message'])) {
				return $payload['error']['message'];
			}
			if (isset($payload['message']) && is_string($payload['message'])) {
				return $payload['message'];
			}
		}

		// Fallback to raw body if present (kept short).
		if (isset($res['body']) && is_string($res['body']) && $res['body'] !== '') {
			return substr($res['body'], 0, 300);
		}

		return '';
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

		// Best-effort: try to locate first {...} block.
		$start = strpos($text, '{');
		$end   = strrpos($text, '}');
		if ($start === false || $end === false || $end <= $start) {
			return null;
		}

		$sub = substr($text, $start, $end - $start + 1);
		$dec = json_decode($sub, true);

		return is_array($dec) ? $dec : null;
	}
}

