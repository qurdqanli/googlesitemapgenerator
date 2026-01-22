<?php
namespace WPNexusAI\Providers;

use WPNexusAI\Settings\SettingsStore;
use WPNexusAI\Utils\HttpClient;

if (!defined('ABSPATH')) { exit; }

final class GeminiAdapter {

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
        $base = rtrim(SettingsStore::str('gemini_base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $model = isset($req['model']) && is_string($req['model']) && $req['model'] !== ''
            ? (string) $req['model']
            : SettingsStore::str('gemini_model', 'gemini-2.5-flash');

        $url = $base . '/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($this->api_key);

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [ ['text' => (string) ($req['input'] ?? '')] ] ],
            ],
        ];

        $inst = (string) ($req['instructions'] ?? '');
        if ($inst !== '') {
            $payload['systemInstruction'] = ['parts' => [ ['text' => $inst] ] ];
        }

        $res = $this->http->post_json($url, $payload, [], 60);
        if (!$res['ok']) {
            $err = is_array($res['body']) && isset($res['body']['error']['message']) ? (string) $res['body']['error']['message'] : ('HTTP ' . $res['code']);
            return ['ok' => false, 'text' => '', 'error' => $err];
        }

        $text = '';
        if (is_array($res['body']) && isset($res['body']['candidates'][0]['content']['parts'][0]['text']) && is_string($res['body']['candidates'][0]['content']['parts'][0]['text'])) {
            $text = (string) $res['body']['candidates'][0]['content']['parts'][0]['text'];
        }

        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'text' => '', 'error' => 'Empty response'];
        }

        return ['ok' => true, 'text' => $text, 'error' => ''];
    }
}

