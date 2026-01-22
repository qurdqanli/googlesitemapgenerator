<?php
namespace WPNexusAI\Providers;

use WPNexusAI\Logging\Logger;
use WPNexusAI\Settings\SettingsStore;
use WPNexusAI\Utils\HttpClient;

if (!defined('ABSPATH')) { exit; }

final class OpenAIAdapter {

    /** @var string */
    private $api_key;

    /** @var Logger */
    private $logger;

    /** @var HttpClient */
    private $http;

    public function __construct(string $api_key) {
        $this->api_key = $api_key;
        $this->logger = Logger::instance();
        $this->http = new HttpClient();
    }

    /**
     * Request fields:
     * - input (string)
     * - instructions (string)
     * - model (optional)
     * - temperature (optional)
     * - max_output_tokens (optional)
     *
     * @param array<string, mixed> $req
     * @return array{ok:bool, text:string, error:string}
     */
    public function translate(array $req): array {
        $base = rtrim(SettingsStore::str('openai_base_url', 'https://api.openai.com/v1'), '/');
        $url = $base . '/responses';

        $model = isset($req['model']) && is_string($req['model']) && $req['model'] !== ''
            ? (string) $req['model']
            : SettingsStore::str('openai_model', 'gpt-5');

        $payload = [
            'model' => $model,
            'input' => (string) ($req['input'] ?? ''),
            'instructions' => (string) ($req['instructions'] ?? ''),
            'store' => false,
        ];

        if (isset($req['temperature'])) { $payload['temperature'] = (float) $req['temperature']; }
        if (isset($req['max_output_tokens'])) { $payload['max_output_tokens'] = (int) $req['max_output_tokens']; }

        $res = $this->http->post_json($url, $payload, [
            'Authorization' => 'Bearer ' . $this->api_key,
        ], 60);

        if (!$res['ok']) {
            $err = is_array($res['body']) && isset($res['body']['error']['message']) ? (string) $res['body']['error']['message'] : ('HTTP ' . $res['code']);
            return ['ok' => false, 'text' => '', 'error' => $err];
        }

        $text = '';
        if (is_array($res['body']) && isset($res['body']['output']) && is_array($res['body']['output'])) {
            foreach ($res['body']['output'] as $item) {
                if (!is_array($item)) { continue; }
                if (($item['type'] ?? '') !== 'message') { continue; }
                $content = $item['content'] ?? [];
                if (!is_array($content)) { continue; }
                foreach ($content as $c) {
                    if (!is_array($c)) { continue; }
                    if (($c['type'] ?? '') === 'output_text' && isset($c['text']) && is_string($c['text'])) {
                        $text .= $c['text'];
                    }
                }
            }
        }

        $text = trim($text);
        if ($text === '') {
            // Some gateways still return chat.completions-like body; try common paths.
            $text = $this->fallback_extract_text($res['body']);
        }

        if ($text === '') {
            return ['ok' => false, 'text' => '', 'error' => 'Empty response'];
        }

        return ['ok' => true, 'text' => $text, 'error' => ''];
    }

    private function fallback_extract_text($body): string {
        if (!is_array($body)) { return ''; }
        // Chat Completions fallback:
        if (isset($body['choices'][0]['message']['content']) && is_string($body['choices'][0]['message']['content'])) {
            return trim((string) $body['choices'][0]['message']['content']);
        }
        // Some proxies:
        if (isset($body['output_text']) && is_string($body['output_text'])) {
            return trim((string) $body['output_text']);
        }
        return '';
    }
}
