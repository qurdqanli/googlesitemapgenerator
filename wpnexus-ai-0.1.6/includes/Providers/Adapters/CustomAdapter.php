<?php
namespace WPNexusAI\Providers\Adapters;

use WP_Error;
use WPNexusAI\Logging\Logger;
use WPNexusAI\Providers\ProviderInterface;
use WPNexusAI\Providers\SelectedKey;
use WPNexusAI\Providers\TranslateRequest;
use WPNexusAI\Providers\TranslateResult;
use WPNexusAI\Providers\Http\JsonHttpClient;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Custom provider adapter.
 *
 * Configure via filters (recommended for now):
 * - wpnexus_ai_custom_endpoint (string) REQUIRED
 * - wpnexus_ai_custom_headers  (array) optional
 *
 * Expected response JSON:
 *  - Either a plain object mapping keys => translated string
 *  - Or { translations: { key: "..." }, usage: {...}, meta: {...} }
 */
final class CustomAdapter implements ProviderInterface {

	/** @var Logger */
	private $logger;

	/** @var JsonHttpClient */
	private $http;

	public function __construct() {
		$this->logger = Logger::instance();
		$this->http   = new JsonHttpClient();
	}

	public function id(): string {
		return 'custom';
	}

	public function translate(TranslateRequest $req, SelectedKey $key) {
		$endpoint = (string) apply_filters('wpnexus_ai_custom_endpoint', '', $req);
		$endpoint = trim($endpoint);

		if ($endpoint === '') {
			$this->logger->warning('providers.custom.not_configured', []);
			return new WP_Error(
				'wpnexus_provider_custom_not_configured',
				t('provider_err_api'),
				[
					'provider' => 'custom',
					'reason'   => 'missing_endpoint',
				]
			);
		}

		$body = [
			'source_lang' => (string) ($req->source_lang ?? 'auto'),
			'target_lang' => (string) ($req->target_lang ?? ''),
			'segments'    => is_array($req->segments) ? $req->segments : [],
			'context'     => is_array($req->context) ? $req->context : [],
			'model'       => (string) ($req->model ?? ''),
			'tone'        => (string) ($req->tone ?? ''),
			'glossary'    => is_array($req->glossary) ? $req->glossary : [],
		];

		$body = apply_filters('wpnexus_ai_custom_body', $body, $req);

		$headers = [
			'Authorization' => 'Bearer ' . $key->key,
		];

		$extra_headers = apply_filters('wpnexus_ai_custom_headers', [], $req);
		if (is_array($extra_headers)) {
			foreach ($extra_headers as $hk => $hv) {
				$hkk = (string) $hk;
				$hvv = (string) $hv;
				if ($hkk !== '' && $hvv !== '') {
					$headers[$hkk] = $hvv;
				}
			}
		}

		$timeout = (int) apply_filters('wpnexus_ai_custom_timeout', 45, $req);

		$this->logger->info('providers.custom.request', [
			'endpoint' => $endpoint,
			'segments' => is_array($body['segments']) ? count($body['segments']) : 0,
		]);

		$res = $this->http->post_json($endpoint, $headers, $body, $timeout);
		if (is_wp_error($res)) {
			return $res;
		}

		$status = (int) ($res['status'] ?? 0);

		if ($status === 429) {
			return new WP_Error('wpnexus_provider_429', t('provider_err_rate_limited'), [
				'provider' => 'custom',
			]);
		}

		if ($status < 200 || $status >= 300) {
			$this->logger->warning('providers.custom.http_error', [
				'status' => $status,
			]);
			return new WP_Error('wpnexus_provider_http_error', t('provider_err_api'), [
				'provider' => 'custom',
				'status'   => $status,
			]);
		}

		$json = isset($res['json']) && is_array($res['json']) ? $res['json'] : null;
		if (!is_array($json)) {
			return new WP_Error('wpnexus_provider_parse_failed', t('provider_err_bad_response'), [
				'provider' => 'custom',
			]);
		}

		// Accept both response shapes:
		// 1) { translations: {...}, usage: {...}, meta: {...} }
		// 2) { key1: "...", key2: "..." }
		$translations_obj = null;
		$usage = [];
		$meta  = [];

		if (isset($json['translations']) && is_array($json['translations'])) {
			$translations_obj = $json['translations'];
			$usage = isset($json['usage']) && is_array($json['usage']) ? $json['usage'] : [];
			$meta  = isset($json['meta']) && is_array($json['meta']) ? $json['meta'] : [];
		} else {
			$translations_obj = $json;
		}

		if (!is_array($translations_obj)) {
			return new WP_Error('wpnexus_provider_parse_failed', t('provider_err_bad_response'), [
				'provider' => 'custom',
			]);
		}

		// Build final translations map by segment keys.
		$translations = [];
		$segments = is_array($req->segments) ? $req->segments : [];
		foreach ($segments as $seg) {
			if (!is_array($seg)) {
				continue;
			}
			$k = isset($seg['key']) ? sanitize_key((string) $seg['key']) : '';
			if ($k === '') {
				continue;
			}
			$translations[$k] = isset($translations_obj[$k]) ? (string) $translations_obj[$k] : '';
		}

		$meta = is_array($meta) ? $meta : [];
		$meta['endpoint'] = $endpoint;

		return new TranslateResult('custom', (int) $key->id, $translations, $usage, $meta);
	}
}

