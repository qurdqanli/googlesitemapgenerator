<?php
namespace WPNexusAI\Services;

if (!defined('ABSPATH')) { exit; }

final class InternalLinker {

    /**
     * @param string $html
     * @param array<int, array<string, mixed>> $rules Each: keyword, url, max(optional), nofollow(optional)
     */
    public function apply(string $html, array $rules): string {
        if (!$rules) { return $html; }

        $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) { return $html; }

        $out = '';
        $in_a = false;

        foreach ($parts as $part) {
            if ($part === '') { continue; }

            if ($part[0] === '<') {
                $tag = strtolower($part);

                if (preg_match('/^<a\b/', $tag)) {
                    $in_a = true;
                } elseif (preg_match('/^<\/a\b/', $tag)) {
                    $in_a = false;
                }

                $out .= $part;
                continue;
            }

            // Text node.
            if ($in_a) {
                $out .= $part;
                continue;
            }

            $text = $part;
            foreach ($rules as $r) {
                $kw = isset($r['keyword']) ? (string) $r['keyword'] : '';
                $url = isset($r['url']) ? (string) $r['url'] : '';
                if ($kw === '' || $url === '') { continue; }

                $max = isset($r['max']) ? (int) $r['max'] : 1;
                $nofollow = !empty($r['nofollow']);

                $rel = $nofollow ? 'nofollow sponsored' : 'sponsored';

                // Replace whole-word matches, case-insensitive.
                $pattern = '/\b' . preg_quote($kw, '/') . '\b/iu';

                $count = 0;
                $text = preg_replace_callback($pattern, function ($m) use ($url, $rel, $max, &$count) {
                    if ($max > 0 && $count >= $max) {
                        return $m[0];
                    }
                    $count++;
                    $label = $m[0];
                    return '<a href="' . esc_url($url) . '" rel="' . esc_attr($rel) . '">' . esc_html($label) . '</a>';
                }, $text);
            }

            $out .= $text;
        }

        return $out;
    }
}
