<?php
namespace WPNexusAI\Extraction;

if (!defined('ABSPATH')) { exit; }

final class SeoExtractor {

    /** @return array<string, mixed> */
    public function extract(int $post_id): array {
        $keys = [
            // Yoast
            '_yoast_wpseo_title',
            '_yoast_wpseo_metadesc',
            '_yoast_wpseo_focuskw',
            '_yoast_wpseo_opengraph-title',
            '_yoast_wpseo_opengraph-description',
            '_yoast_wpseo_twitter-title',
            '_yoast_wpseo_twitter-description',
            '_yoast_wpseo_canonical',
            // RankMath
            'rank_math_title',
            'rank_math_description',
            'rank_math_focus_keyword',
            'rank_math_canonical_url',
            'rank_math_facebook_title',
            'rank_math_facebook_description',
            'rank_math_twitter_title',
            'rank_math_twitter_description',
        ];

        $out = [];
        foreach ($keys as $k) {
            $v = get_post_meta($post_id, $k, true);
            if ($v !== '' && $v !== null) {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
