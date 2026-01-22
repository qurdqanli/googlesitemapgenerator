<?php
namespace WPNexusAI\Extraction;

use WPNexusAI\Rules\RulesRepo;
use WPNexusAI\Services\TranslateService;

if (!defined('ABSPATH')) { exit; }

final class MetaExtractor {

    /** @var RulesRepo */
    private $rules;

    /** @var TranslateService */
    private $translate;

    public function __construct() {
        $this->rules = new RulesRepo();
        $this->translate = new TranslateService();
    }

    /**
     * Extract meta and ACF mappings from rule config.
     *
     * Rule meta_map/acf_map are arrays of {source, target}.
     *
     * @param int $post_id
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $ctx
     * @return array{meta:array<string,mixed>, acf:array<string,mixed>}
     */
    public function extract(int $post_id, array $rule, array $ctx): array {
        $meta = [];
        $acf  = [];

        $translate_meta = !empty($rule['translate_meta']);

        $meta_map = $this->rules->meta_map($rule);
        foreach ($meta_map as $m) {
            $src = isset($m['source']) ? (string) $m['source'] : '';
            $dst = isset($m['target']) ? (string) $m['target'] : '';
            if ($src === '' || $dst === '') { continue; }
            $v = get_post_meta($post_id, $src, true);
            if (is_string($v) && $translate_meta) {
                $v = $this->translate->translate($v, $ctx);
            }
            $meta[$dst] = $v;
        }

        $acf_map = $this->rules->acf_map($rule);
        foreach ($acf_map as $m) {
            $src = isset($m['source']) ? (string) $m['source'] : '';
            $dst = isset($m['target']) ? (string) $m['target'] : '';
            if ($src === '' || $dst === '') { continue; }
            $v = get_post_meta($post_id, $src, true);
            if (is_string($v) && $translate_meta) {
                $v = $this->translate->translate($v, $ctx);
            }
            $acf[$dst] = $v;
        }

        return ['meta' => $meta, 'acf' => $acf];
    }
}
