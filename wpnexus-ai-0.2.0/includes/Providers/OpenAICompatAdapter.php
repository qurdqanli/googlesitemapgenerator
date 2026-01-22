<?php
namespace WPNexusAI\Providers;

use WPNexusAI\Settings\SettingsStore;
use WPNexusAI\Utils\HttpClient;

if (!defined('ABSPATH')) { exit; }

/**
 * OpenAI-compatible chat completions adapter (for gateways like OpenRouter, local proxies, etc).
 */
final class OpenAICompatAdapter {

    /** @var string */
    private $api_key;

    /** @var HttpClient */
    private $http;

    public function __construct(string $api_key) {
        $this->api_key = $api_key;
        $this->http = new HttpClient();
    }

    /**
     * @param array<string,mixed> $req
     * @return array{ok:bool, text:string, error:string}
     */
    public function translate(array $req): array {
        $base = rtrim(SettingsStore::str('openai_compat_base_url', ''), '/');
        if ($base === '') {
            return ['ok' => false, 'text' => '', 'error' => 'OpenAI-compat base URL is empty'];
        }
        $url = $base . '/chat/completions';

        $model = isset($req['model']) && is_string($req['model']) && $req['model'] !== ''
            ? (string) $req['model']
            : SettingsStore::str('openai_compat_model', 'gpt-4o-mini');

        $max = isset($req['max_output_tokens']) ? (int) $req['max_output_tokens'] : 4000;
        $temp = isset($req['temperature']) ? (float) $req['temperature'] : 0.4;

        $system = (string) ($req['instructions'] ?? '');
        $input = (string) ($req['input'] ?? '');

        $payload = [
            'model' => $model,
            'temperature' => max(0.0, min(1.0, $temp)),
            'max_tokens' => max(1, min(8192, $max)),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $input],
            ],
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
        ];

        $res = $this->http->post_json($url, $payload, $headers, 60);
        if (!$res['ok']) {
            $err = is_array($res['body']) && isset($res['body']['error']['message']) ? (string) $res['body']['error']['message'] : ('HTTP ' . $res['code']);
            return ['ok' => false, 'text' => '', 'error' => $err];
        }

        $text = '';
        if (is_array($res['body']) && isset($res['body']['choices'][0]['message']['content']) && is_string($res['body']['choices'][0]['message']['content'])) {
            $text = (string) $res['body']['choices'][0]['message']['content'];
        }
        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'text' => '', 'error' => 'Empty response'];
        }
        return ['ok' => true, 'text' => $text, 'error' => ''];
    }
}
