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
	 * @param array<string,string> $headers
	 * @param array<string,mixed> $body
	 * @return array{status:int,headers:array<string,mixed>,body_raw:string,json:array<string,mixed>|null}|WP_Error
	 */
	public function post_json(string $url, array $headers, array $body, int $timeout = 30) {
		$url = esc_url_raw($url);
		$timeout = max(5, min(60, (int) $timeout));

		$payload = wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($payload)) {
			return new WP_Error('wpnexus_http_json_encode_failed', 'Failed to encode JSON body.');
		}

		$headers = array_merge([
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		], $headers);

		$this->logger->debug('providers.http.post', [
			'host'    => wp_parse_url($url, PHP_URL_HOST),
			'path'    => wp_parse_url($url, PHP_URL_PATH),
			'timeout' => $timeout,
		]);

		$res = wp_remote_post($url, [
			'timeout' => $timeout,
			'headers' => $headers,
			'body'    => $payload,
		]);

		if (is_wp_error($res)) {
			$this->logger->warning('providers.http.fail', [
				'code' => $res->get_error_code(),
				'msg'  => $res->get_error_message(),
			]);
			return $res;
		}

		$status = (int) wp_remote_retrieve_response_code($res);
		$raw    = (string) wp_remote_retrieve_body($res);
		$h      = wp_remote_retrieve_headers($res);
		$headers_out = is_array($h) ? $h : (method_exists($h, 'getAll') ? $h->getAll() : []);

		$json = null;
		$dec = json_decode($raw, true);
		if (is_array($dec)) {
			$json = $dec;
		}

		$this->logger->debug('providers.http.done', [
			'status' => $status,
		]);

		return [
			'status'   => $status,
			'headers'  => $headers_out,
			'body_raw' => $raw,
			'json'     => $json,
		];
	}
}
