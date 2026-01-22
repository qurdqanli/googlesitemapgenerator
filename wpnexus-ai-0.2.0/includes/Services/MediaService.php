<?php
namespace WPNexusAI\Services;

use WPNexusAI\Keys\KeysRepo;
use WPNexusAI\Providers\Image\OpenAIImageProvider;
use WPNexusAI\Settings\SettingsStore;

if (!defined('ABSPATH')) { exit; }

final class MediaService {

    /** @var KeysRepo */
    private $keys;

    public function __construct() {
        $this->keys = new KeysRepo();
    }

    /**
     * Build featured image payload based on rule.
     * @param int $post_id
     * @param array<string, mixed> $rule
     * @param string $translated_text_for_prompt
     * @return array<string, mixed>|null
     */
    public function featured_image_payload(int $post_id, array $rule, string $translated_text_for_prompt): ?array {
        $mode = (string) ($rule['image_mode'] ?? 'keep');
        if ($mode === 'none') {
            return null;
        }

        if ($mode === 'generate') {
            $prompt = (string) ($rule['image_prompt'] ?? '');
            if (trim($prompt) === '') {
                // Derive prompt from content (keep short).
                $prompt = $this->derive_prompt($translated_text_for_prompt);
            }

            $key = $this->pick_openai_key();
            if ($key === '') { return null; }

            $provider = new OpenAIImageProvider($key);
            $res = $provider->generate($prompt);
            if (!$res['ok']) { return null; }

            if ($res['b64'] !== '') {
                return [
                    'mode' => 'b64',
                    'mime' => 'image/png',
                    'filename' => 'wpnexus-' . $post_id . '-' . time() . '.png',
                    'data_b64' => $res['b64'],
                    'prompt' => $prompt,
                ];
            }

            if ($res['url'] !== '') {
                return [
                    'mode' => 'url',
                    'url' => $res['url'],
                    'prompt' => $prompt,
                ];
            }

            return null;
        }

        // keep: send source featured image URL if exists.
        $thumb_id = (int) get_post_thumbnail_id($post_id);
        if ($thumb_id > 0) {
            $url = wp_get_attachment_url($thumb_id);
            if (is_string($url) && $url !== '') {
                return ['mode' => 'url', 'url' => $url];
            }
        }

        return null;
    }

    /** @return array<int, string> */
    public function extract_content_image_urls(string $html): array {
        if (trim($html) === '') { return []; }
        $urls = [];
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
            foreach ($m[1] as $u) {
                $u = trim((string) $u);
                if ($u !== '' && preg_match('/^https?:\/\//i', $u)) {
                    $urls[] = $u;
                }
            }
        }
        return array_values(array_unique($urls));
    }

    private function pick_openai_key(): string {
        $rows = $this->keys->all(true);
        $keys = [];
        foreach ($rows as $row) {
            if ((string) $row['provider'] === 'openai') {
                $k = $this->keys->key_plain($row);
                if ($k !== '') { $keys[] = $k; }
            }
        }
        if (!$keys) { return ''; }
        return $keys[array_rand($keys)];
    }

    private function derive_prompt(string $text): string {
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim((string) $text);
        $text = mb_substr($text, 0, 300);
        $style = SettingsStore::str('image_style_hint', 'Editorial, photorealistic, high quality, no text, no watermark.');
        return trim($style . " Create a featured image that matches this article: " . $text);
    }
}
