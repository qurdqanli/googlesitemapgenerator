<?php
namespace WPNexusAI\Providers\Image;

use WPNexusAI\Settings\SettingsStore;
use WPNexusAI\Utils\HttpClient;

if (!defined('ABSPATH')) { exit; }

final class OpenAIImageProvider {

    /** @var string */
    private $api_key;

    /** @var HttpClient */
    private $http;

    public function __construct(string $api_key) {
        $this->api_key = $api_key;
        $this->http = new HttpClient();
    }

    /**
     * @param string $prompt
     * @return array{ok:bool, b64:string, url:string, error:string}
     */
    public function generate(string $prompt): array {
        $base = rtrim(SettingsStore::str('openai_base_url', 'https://api.openai.com/v1'), '/');
        $url = $base . '/images/generations';

        $model = SettingsStore::str('openai_image_model', 'gpt-image-1');
        $size = SettingsStore::str('openai_image_size', '1024x1024');

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'size' => $size,
            'n' => 1,
            'response_format' => 'b64_json',
        ];

        $res = $this->http->post_json($url, $payload, [
            'Authorization' => 'Bearer ' . $this->api_key,
        ], 90);

        if (!$res['ok']) {
            $err = is_array($res['body']) && isset($res['body']['error']['message']) ? (string) $res['body']['error']['message'] : ('HTTP ' . $res['code']);
            return ['ok' => false, 'b64' => '', 'url' => '', 'error' => $err];
        }

        $b64 = '';
        $imgUrl = '';
        if (is_array($res['body']) && isset($res['body']['data'][0])) {
            $d0 = $res['body']['data'][0];
            if (is_array($d0)) {
                if (isset($d0['b64_json']) && is_string($d0['b64_json'])) {
                    $b64 = $d0['b64_json'];
                }
                if (isset($d0['url']) && is_string($d0['url'])) {
                    $imgUrl = $d0['url'];
                }
            }
        }

        if ($b64 === '' && $imgUrl === '') {
            return ['ok' => false, 'b64' => '', 'url' => '', 'error' => 'Empty image response'];
        }

        return ['ok' => true, 'b64' => $b64, 'url' => $imgUrl, 'error' => ''];
    }
}
