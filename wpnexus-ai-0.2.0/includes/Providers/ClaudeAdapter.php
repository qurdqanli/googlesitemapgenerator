<?php
namespace WPNexusAI\Providers;

use WPNexusAI\Settings\SettingsStore;
use WPNexusAI\Utils\HttpClient;

if (!defined('ABSPATH')) { exit; }

/**
 * Anthropic Messages API adapter ("Claude").
 */
final class ClaudeAdapter {

    /** @var string */
    private $api_key;

    /** @var HttpClient */
    private $http;

    public function __construct(string $api_key) {
        $this->api_key = $api_key;
        $this->http = new HttpClient();
    }

    /**
     * @param array<string, mixed> $req
     * @return array{ok:bool, text:string, error:string}
     */
    public function translate(array $req): array {
        $base = rtrim(SettingsStore::str('claude_base_url', 'https://api.anthropic.com/v1'), '/');
        $url = $base . '/messages';

        $model = isset($req['model']) && is_string($req['model']) && $req['model'] !== ''
            ? (string) $req['model']
            : SettingsStore::str('claude_model', 'claude-3-5-sonnet-latest');

        $max = isset($req['max_output_tokens']) ? (int) $req['max_output_tokens'] : 4000;
        $temp = isset($req['temperature']) ? (float) $req['temperature'] : 0.4;

        $system = (string) ($req['instructions'] ?? '');
        $input = (string) ($req['input'] ?? '');

        $payload = [
            'model' => $model,
            'max_tokens' => max(1, min(8192, $max)),
            'temperature' => max(0.0, min(1.0, $temp)),
            'system' => $system,
            'messages' => [
                ['role' => 'user', 'content' => $input],
            ],
        ];

        $res = $this->http->post_json($url, $payload, [
            'x-api-key' => $this->api_key,
            'anthropic-version' => '2023-06-01',
        ], 60);

        if (!$res['ok']) {
            $err = is_array($res['body']) && isset($res['body']['error']['message']) ? (string) $res['body']['error']['message'] : ('HTTP ' . $res['code']);
            return ['ok' => false, 'text' => '', 'error' => $err];
        }

        $text = '';
        if (is_array($res['body']) && isset($res['body']['content']) && is_array($res['body']['content'])) {
            foreach ($res['body']['content'] as $c) {
                if (!is_array($c)) continue;
                if (($c['type'] ?? '') === 'text' && isset($c['text']) && is_string($c['text'])) {
                    $text .= $c['text'];
                }
            }
        }

        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'text' => '', 'error' => 'Empty response'];
        }

        return ['ok' => true, 'text' => $text, 'error' => ''];
    }
}
