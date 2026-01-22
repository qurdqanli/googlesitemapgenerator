<?php
namespace WPNexusAI\Extraction;

use WPNexusAI\Services\TranslateService;
use WPNexusAI\Rules\RulesRepo;

if (!defined('ABSPATH')) { exit; }

final class PostExtractor {

    /** @var TranslateService */
    private $translate;

    /** @var MetaExtractor */
    private $meta;

    /** @var SeoExtractor */
    private $seo;

    public function __construct() {
        $this->translate = new TranslateService();
        $this->meta = new MetaExtractor();
        $this->seo = new SeoExtractor();
    }

    /**
     * Extract and optionally translate post fields.
     *
     * @param int $post_id
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $target
     * @return array<string, mixed>
     */
    public function build_payload(int $post_id, array $rule, array $target): array {
        $post = get_post($post_id);
        if (!$post) { return []; }

        $source_lang = $this->site_lang();
        $target_lang = (string) ($target['target_lang'] ?? '');

        $ctx = [
            'source_lang' => $source_lang,
            'target_lang' => $target_lang,
            'persona' => (string) ($rule['persona'] ?? 'neutral'),
            'custom_prompt' => (string) ($rule['custom_prompt'] ?? ''),
        ];

        $title = $this->translate->translate((string) $post->post_title, $ctx);
        $content = $this->translate->translate((string) $post->post_content, $ctx);
        $excerpt = $this->translate->translate((string) $post->post_excerpt, $ctx);

        // Taxonomies
        $tax_payload = $this->taxonomies_payload($post_id, $rule, $ctx);

        // Meta/ACF mapping
        $meta_payload = $this->meta->extract($post_id, $rule, $ctx);

        // SEO meta
        $seo_payload = $this->seo->extract($post_id);

        return [
            'source_post_id' => $post_id,
            'post_type' => (string) $post->post_type,
            'status' => 'publish', // target status (can be configurable)
            'slug' => (string) $post->post_name,
            'date_gmt' => (string) $post->post_date_gmt,
            'title' => $title,
            'content' => $content,
            'excerpt' => $excerpt,
            'taxonomies' => $tax_payload,
            'meta' => $meta_payload['meta'],
            'acf' => $meta_payload['acf'],
            'seo' => $seo_payload,
        ];
    }

    private function site_lang(): string {
        $locale = determine_locale();
        // Map locale to short lang code.
        $parts = explode('_', $locale);
        return strtolower($parts[0] ?? $locale);
    }

    /** @return array<string, mixed> */
    private function taxonomies_payload(int $post_id, array $rule, array $ctx): array {
        $translate_tax = !empty($rule['translate_taxonomies']);

        $out = [];

        $taxes = ['category', 'post_tag', 'product_cat', 'product_tag'];
        foreach ($taxes as $tax) {
            if (!taxonomy_exists($tax)) { continue; }

            $terms = wp_get_post_terms($post_id, $tax, ['fields' => 'all']);
            if (is_wp_error($terms) || !is_array($terms) || !$terms) { continue; }

            $items = [];
            foreach ($terms as $t) {
                if (!$t || !isset($t->term_id)) { continue; }

                $name = (string) $t->name;
                if ($translate_tax) {
                    $name = $this->translate->translate($name, $ctx);
                }

                $items[] = [
                    'taxonomy' => $tax,
                    'source_term_id' => (int) $t->term_id,
                    'name' => $name,
                    'slug' => sanitize_title($name),
                ];
            }

            if ($items) {
                $out[$tax] = $items;
            }
        }

        return $out;
    }
}
