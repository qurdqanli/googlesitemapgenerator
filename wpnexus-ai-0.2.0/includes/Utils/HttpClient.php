<?php
namespace WPNexusAI\Utils;

use WPNexusAI\Logging\Logger;

if (!defined('ABSPATH')) { exit; }

final class HttpClient {

    /** @var Logger */
    private $logger;

    public function __construct() {
        $this->logger = Logger::instance();
    }

    /**
     * @return array{ok:bool, code:int, body:mixed, raw:string}
     */
    public function post_json(string $url, array $payload, array $headers = [], int $timeout = 30): array {
        $args = [
            'method'  => 'POST',
            'timeout' => $timeout,
            'headers' => array_merge([
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept'       => 'application/json',
            ], $headers),
            'body'    => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        $res = wp_remote_request($url, $args);
        if (is_wp_error($res)) {
            $this->logger->error('http.post_json.wp_error', ['url' => $url, 'err' => $res->get_error_message()]);
            return ['ok' => false, 'code' => 0, 'body' => null, 'raw' => ''];
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        $raw = (string) wp_remote_retrieve_body($res);
        $body = null;

        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $body = $decoded;
            } else {
                $body = $raw;
            }
        }

        $ok = $code >= 200 && $code < 300;
        if (!$ok) {
            $this->logger->warn('http.post_json.non_2xx', ['url' => $url, 'code' => $code, 'raw' => substr($raw, 0, 2000)]);
        }
        return ['ok' => $ok, 'code' => $code, 'body' => $body, 'raw' => $raw];
    }

    /**
     * @return array{ok:bool, code:int, raw:string}
     */
    public function get(string $url, array $headers = [], int $timeout = 30): array {
        $args = [
            'method'  => 'GET',
            'timeout' => $timeout,
            'headers' => $headers,
        ];
        $res = wp_remote_request($url, $args);
        if (is_wp_error($res)) {
            $this->logger->error('http.get.wp_error', ['url' => $url, 'err' => $res->get_error_message()]);
            return ['ok' => false, 'code' => 0, 'raw' => ''];
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        $raw = (string) wp_remote_retrieve_body($res);
        return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'raw' => $raw];
    }
}
