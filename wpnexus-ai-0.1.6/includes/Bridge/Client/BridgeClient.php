<?php
namespace WPNexusAI\Bridge\Client;

use WP_Error;
use WPNexusAI\Logging\Logger;
use WPNexusAI\DB\Repos\TargetsRepo;

if (!defined('ABSPATH')) {
	exit;
}

final class BridgeClient {

	/** @var Logger */
	private $logger;

	/** @var TargetsRepo */
	private $targets;

	public function __construct() {
		$this->logger  = Logger::instance();
		$this->targets = new TargetsRepo();
	}

	/**
	 * Convenience: GET /health
	 *
	 * @param array<string,mixed> $target_row
	 */
	public function health(array $target_row): BridgeResponse {
		return $this->request($target_row, 'GET', '/wp-json/wpnexus-bridge/v1/health', [
			'timeout' => 8,
		]);
	}

	/**
	 * Convenience: GET /site
	 *
	 * @param array<string,mixed> $target_row
	 */
	public function site(array $target_row): BridgeResponse {
		return $this->request($target_row, 'GET', '/wp-json/wpnexus-bridge/v1/site', [
			'timeout' => 12,
		]);
	}

	/**
	 * Convenience: GET /languages
	 *
	 * @param array<string,mixed> $target_row
	 */
	public function languages(array $target_row): BridgeResponse {
		return $this->request($target_row, 'GET', '/wp-json/wpnexus-bridge/v1/languages', [
			'timeout' => 12,
		]);
	}

	/**
	 * Convenience: POST /posts/upsert
	 *
	 * @param array<string,mixed> $target_row
	 * @param array<string,mixed> $payload
	 */
	public function posts_upsert(array $target_row, array $payload): BridgeResponse {
		$body = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($body) || $body === '') {
			$body = '{}';
		}

		return $this->request($target_row, 'POST', '/wp-json/wpnexus-bridge/v1/posts/upsert', [
			'timeout' => 30,
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body' => $body,
		]);
	}

	/**
	 * Convenience: POST /posts/delete
	 *
	 * @param array<string,mixed> $target_row
	 * @param array<string,mixed> $payload
	 */
	public function posts_delete(array $target_row, array $payload): BridgeResponse {
		$body = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($body) || $body === '') {
			$body = '{}';
		}

		return $this->request($target_row, 'POST', '/wp-json/wpnexus-bridge/v1/posts/delete', [
			'timeout' => 30,
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body' => $body,
		]);
	}

	/**
	 * Convenience: POST /terms/upsert
	 *
	 * @param array<string,mixed> $target_row
	 * @param array<string,mixed> $payload
	 */
	public function terms_upsert(array $target_row, array $payload): BridgeResponse {
		$body = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($body) || $body === '') {
			$body = '{}';
		}

		return $this->request($target_row, 'POST', '/wp-json/wpnexus-bridge/v1/terms/upsert', [
			'timeout' => 20,
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body' => $body,
		]);
	}

	/**
	 * Universal request wrapper.
	 *
	 * @param array<string,mixed> $target_row
	 * @param string $method
	 * @param string $path absolute path starting with /
	 * @param array<string,mixed> $opts supports: timeout, headers, body, query
	 */

	/**
	 * Upload a media file to the Bridge (/media/upload).
	 * multipart/form-data + optional signature for idempotency.
	 */
	public function media_upload(array $target_row, string $file_path, string $filename = '', string $mime = 'application/octet-stream', string $signature = '', string $source_url = ''): BridgeResponse {
		$file_path = (string) $file_path;
		if ($filename === '') { $filename = basename($file_path); }
		if (!file_exists($file_path) || !is_readable($file_path)) {
			return BridgeResponse::from(['ok'=>false,'status'=>0,'error'=>t('media_err_missing_file')]);
		}

		$contents = file_get_contents($file_path);
		if (!is_string($contents)) { $contents = ''; }

		$boundary = wp_generate_password(24, false, false);
		$eol = "\r\n";
		$body = '';

		if ($signature !== '') {
			$body .= '--' . $boundary . $eol;
			$body .= 'Content-Disposition: form-data; name="signature"' . $eol . $eol;
			$body .= sanitize_text_field($signature) . $eol;
		}
		if ($source_url !== '') {
			$body .= '--' . $boundary . $eol;
			$body .= 'Content-Disposition: form-data; name="source_url"' . $eol . $eol;
			$body .= esc_url_raw($source_url) . $eol;
		}

		$body .= '--' . $boundary . $eol;
		$body .= 'Content-Disposition: form-data; name="file"; filename="' . sanitize_file_name($filename) . '"' . $eol;
		$body .= 'Content-Type: ' . sanitize_text_field($mime) . $eol . $eol;
		$body .= $contents . $eol;
		$body .= '--' . $boundary . '--' . $eol;

		return $this->request($target_row, 'POST', '/wp-json/wpnexus-bridge/v1/media/upload', [
			'timeout' => 60,
			'headers' => ['Content-Type' => 'multipart/form-data; boundary=' . $boundary],
			'body' => $body,
		]);
	}

	public function request(array $target_row, string $method, string $path, array $opts = []): BridgeResponse {
		$method = strtoupper((string) $method);

		$base_url = isset($target_row['base_url']) ? (string) $target_row['base_url'] : '';
		$base_url = rtrim($base_url, '/');
		$path     = '/' . ltrim((string) $path, '/');

		$url = $base_url . $path;

		// Query support
		if (!empty($opts['query']) && is_array($opts['query'])) {
			$q = [];
			foreach ($opts['query'] as $k => $v) {
				$k = sanitize_key((string) $k);
				if ($k === '') {
					continue;
				}
				$q[$k] = is_scalar($v) ? (string) $v : '';
			}
			if (!empty($q)) {
				$url = add_query_arg($q, $url);
			}
		}

		$timeout = isset($opts['timeout']) ? (int) $opts['timeout'] : 15;
		$timeout = max(5, min(60, $timeout));

		$headers = $this->base_headers($target_row);

		if (!empty($opts['headers']) && is_array($opts['headers'])) {
			foreach ($opts['headers'] as $k => $v) {
				$headers[(string) $k] = (string) $v;
			}
		}

		$args = [
			'method'      => $method,
			'timeout'     => $timeout,
			'redirection' => 3,
			'headers'     => $headers,
		];

		if (isset($opts['body'])) {
			$args['body'] = $opts['body'];
		}

		$this->logger->debug('bridge.client.request.start', [
			'method'  => $method,
			'url'     => $url,
			'timeout' => $timeout,
		]);

		$response = wp_remote_request($url, $args);

		$result = [
			'ok'      => false,
			'status'  => null,
			'error'   => null,
			'json'    => null,
			'raw'     => null,
			'headers' => [],
		];

		if (is_wp_error($response)) {
			/** @var WP_Error $response */
			$result['error'] = $response->get_error_message();

			$this->logger->warning('bridge.client.request.wp_error', [
				'method' => $method,
				'url'    => $url,
				'error'  => $result['error'],
			]);

			return BridgeResponse::from($result);
		}

		$status = (int) wp_remote_retrieve_response_code($response);
		$body   = (string) wp_remote_retrieve_body($response);
		$hdrs   = wp_remote_retrieve_headers($response);

		$result['status']  = $status;
		$result['headers'] = is_array($hdrs) ? $hdrs : (method_exists($hdrs, 'getAll') ? $hdrs->getAll() : []);
		$result['raw']     = $this->safe_body_snippet($body);

		$decoded = json_decode($body, true);
		if (is_array($decoded)) {
			$result['json'] = $decoded;
		}

		$result['ok'] = ($status >= 200 && $status < 300);

		if (!$result['ok']) {
			$err = null;

			// Try standard WP REST shape
			if (is_array($result['json'])) {
				if (!empty($result['json']['message'])) {
					$err = (string) $result['json']['message'];
				} elseif (!empty($result['json']['error'])) {
					$err = (string) $result['json']['error'];
				}
			}

			if (!$err) {
				$err = $result['raw'] ?: ('HTTP ' . $status);
			}

			$result['error'] = $err;

			$this->logger->warning('bridge.client.request.http_fail', [
				'method' => $method,
				'url'    => $url,
				'status' => $status,
				'error'  => $err,
			]);

			return BridgeResponse::from($result);
		}

		$this->logger->debug('bridge.client.request.ok', [
			'method' => $method,
			'url'    => $url,
			'status' => $status,
		]);

		return BridgeResponse::from($result);
	}

	/**
	 * @param array<string,mixed> $target_row
	 * @return array<string,string>
	 */
	private function base_headers(array $target_row): array {
		$headers = [
			'Accept'     => 'application/json',
			'User-Agent' => 'WPNexusAI-Core/' . (defined('WPNEXUS_AI_VERSION') ? WPNEXUS_AI_VERSION : 'dev'),
		];

		// Multisite routing (optional)
		if (isset($target_row['network_site_id']) && $target_row['network_site_id'] !== null && $target_row['network_site_id'] !== '') {
			$headers['X-WPNexus-Network-Site'] = (string) $target_row['network_site_id'];
		}

		$auth_method = isset($target_row['auth_method']) ? sanitize_key((string) $target_row['auth_method']) : '';

		$secrets = null;
		if (isset($target_row['secrets']) && is_array($target_row['secrets'])) {
			$secrets = $target_row['secrets'];
		} elseif (isset($target_row['secrets_cipher']) && is_string($target_row['secrets_cipher']) && $target_row['secrets_cipher'] !== '') {
			$secrets = $this->targets->decode_secrets((string) $target_row['secrets_cipher']);
		}

		if ($auth_method === 'bridge_token') {
			$token = is_array($secrets) && !empty($secrets['token']) ? (string) $secrets['token'] : '';
			if ($token !== '') {
				$headers['Authorization'] = 'Bearer ' . $token;
			}
		} elseif ($auth_method === 'app_password') {
			$user = isset($target_row['auth_user']) ? (string) $target_row['auth_user'] : '';
			$pass = is_array($secrets) && !empty($secrets['app_password']) ? (string) $secrets['app_password'] : '';

			if ($user !== '' && $pass !== '') {
				$headers['Authorization'] = 'Basic ' . base64_encode($user . ':' . $pass);
			}
		}

		return $headers;
	}

	private function safe_body_snippet(string $body): string {
		$body = trim($body);
		if ($body === '') {
			return '';
		}
		return function_exists('mb_substr') ? mb_substr($body, 0, 1000) : substr($body, 0, 1000);
	}
}
