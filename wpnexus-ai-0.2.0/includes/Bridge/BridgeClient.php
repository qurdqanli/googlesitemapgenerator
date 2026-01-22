<?php
namespace WPNexusAI\Bridge;

use WPNexusAI\Utils\HttpClient;

if (!defined('ABSPATH')) { exit; }

final class BridgeClient {

    /** @var HttpClient */
    private $http;

    public function __construct() {
        $this->http = new HttpClient();
    }

    /**
     * @param string $base_url
     * @param string $token
     * @return array{ok:bool, code:int, body:mixed, raw:string}
     */
    public function ping(string $base_url, string $token): array {
        $url = rtrim($base_url, '/') . '/wp-json/wpnexus-ai-bridge/v1/ping';
        return $this->http->post_json($url, ['ts' => time()], [
            'Authorization' => 'Bearer ' . $token,
        ], 20);
    }

    /**
     * @param string $base_url
     * @param string $token
     * @param array<string, mixed> $payload
     * @return array{ok:bool, code:int, body:mixed, raw:string}
     */
    public function upsert(string $base_url, string $token, array $payload): array {
        $url = rtrim($base_url, '/') . '/wp-json/wpnexus-ai-bridge/v1/upsert';
        return $this->http->post_json($url, $payload, [
            'Authorization' => 'Bearer ' . $token,
        ], 90);
    }
}
