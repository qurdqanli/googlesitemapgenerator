<?php
namespace WPNexusAI\Providers\Http;

use WP_Error;
use WPNexusAI\Logging\Logger;

if (!defined('ABSPATH')) {
	exit;
}

final class JsonHttpClient {

	/** @var Logger */
	private $logger;

	public function __construct() {
		$this->logger = Logger::instance();
	}

	/**
	 * POST JSON and return structured response.
	 *
	 * @param string               $url
	 * @param array<string,string> $headers
	 * @param array<string,mixed>  $body
	 * @param int                 $timeout_seconds
	 * @return array<string,mixed>|WP_Error
	 */
	public function post_json(string $url, array $headers, array $body, int $timeout_seconds = 45) {
		return $this->request_json('POST', $url, $headers, $body, $timeout_seconds);
	}

	/**
	 * Low-level JSON request helper.
	 *
	 * Returns array:
	 *  - status   (int)
	 *  - headers  (array)
	 *  - body_raw (string)
	 *  - json     (array|null)
	 *
	 * Network/transport errors return WP_Error.
	 *
	 * @param string               $method
	 * @param string               $url
	 * @param array<string,string> $headers
	 * @param array<string,mixed>  $body
	 * @param int                 $timeout_seconds
	 * @return array<string,mixed>|WP_Error
	 */
	private function request_json(string $method, string $url, array $headers, array $body, int $timeout_seconds) {
		$method = strtoupper(trim($method));
		$url    = trim($url);

		if ($url === '' || !preg_match('#^https?://#i', $url)) {
			return new WP_Error('wpnexus_http_invalid_url', 'Invalid URL.');
		}

		$timeout_seconds = max(5, min(120, (int) $timeout_seconds));

		$headers = $this->normalize_headers($headers);
		$headers = array_merge([
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json; charset=utf-8',
		], $headers);

		$payload = wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if (!is_string($payload) || $payload === '') {
			return new WP_Error('wpnexus_http_encode_failed', 'Failed to encode JSON body.');
		}

		$args = [
			'method'      => $method,
			'timeout'     => $timeout_seconds,
			'redirection' => 3,
			'blocking'    => true,
			'headers'     => $headers,
			'body'        => $payload,
			'user-agent'  => $this->user_agent(),
		];

		$args = apply_filters('wpnexus_ai_http_args', $args, $method, $url, $body);

		$this->logger->debug('providers.http.start', [
			'method'  => $method,
			'url'     => $this->safe_url_for_log($url),
			'timeout' => $timeout_seconds,
			'bytes'   => strlen($payload),
		]);

		$res = wp_remote_request($url, $args);

		if (is_wp_error($res)) {
			$this->logger->warning('providers.http.transport_error', [
				'url'   => $this->safe_url_for_log($url),
				'code'  => $res->get_error_code(),
				'error' => $res->get_error_message(),
			]);

			return new WP_Error(
				'wpnexus_http_transport',
				$res->get_error_message(),
				[
					'url'  => $url,
					'code' => $res->get_error_code(),
				]
			);
		}

		$status = (int) wp_remote_retrieve_response_code($res);
		$raw    = (string) wp_remote_retrieve_body($res);

		$headers_out = wp_remote_retrieve_headers($res);
		if ($headers_out instanceof \Requests_Utility_CaseInsensitiveDictionary) {
			$headers_out = $headers_out->getAll();
		}
		if (!is_array($headers_out)) {
			$headers_out = [];
		}

		$json = null;
		if ($raw !== '') {
			$dec = json_decode($raw, true);
			if (is_array($dec)) {
				$json = $dec;
			}
		}

		$this->logger->debug('providers.http.done', [
			'status' => $status,
			'url'    => $this->safe_url_for_log($url),
			'bytes'  => strlen($raw),
			'json'   => is_array($json) ? 1 : 0,
		]);

		return [
			'status'   => $status,
			'headers'  => $headers_out,
			'body_raw' => $raw,
			'json'     => $json,
		];
	}

	/**
	 * @param array<string,mixed> $headers
	 * @return array<string,string>
	 */
	private function normalize_headers(array $headers): array {
		$out = [];
		foreach ($headers as $k => $v) {
			$kk = trim((string) $k);
			if ($kk === '') {
				continue;
			}
			if (is_scalar($v) || (is_object($v) && method_exists($v, '__toString'))) {
				$out[$kk] = (string) $v;
			}
		}
		return $out;
	}

	private function user_agent(): string {
		$ver = defined('WPNEXUS_AI_VERSION') ? (string) WPNEXUS_AI_VERSION : 'dev';
		return 'WPNexusAI/' . $ver . '; ' . home_url('/');
	}

	private function safe_url_for_log(string $url): string {
		// Avoid logging secrets in query strings.
		$parts = wp_parse_url($url);
		if (!is_array($parts) || empty($parts['host'])) {
			return $url;
		}

		$scheme = isset($parts['scheme']) ? $parts['scheme'] : 'https';
		$host   = $parts['host'];
		$port   = isset($parts['port']) ? ':' . $parts['port'] : '';
		$path   = isset($parts['path']) ? $parts['path'] : '';

		return $scheme . '://' . $host . $port . $path;
	}
}

