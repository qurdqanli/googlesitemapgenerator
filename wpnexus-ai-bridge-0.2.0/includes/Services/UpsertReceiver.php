<?php
namespace WPNexusAIBridge\Services;

use WPNexusAIBridge\Logging\Logger;
use WPNexusAIBridge\Utils\MediaHelper;

if (!defined('ABSPATH')) { exit; }

final class UpsertReceiver {

    /** @var Logger */
    private $logger;

    /** @var MediaHelper */
    private $media;

    public function __construct() {
        $this->logger = Logger::instance();
        $this->media = new MediaHelper();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function handle(array $payload): array {
        $identity = isset($payload['identity']) && is_array($payload['identity']) ? $payload['identity'] : [];
        $post = isset($payload['post']) && is_array($payload['post']) ? $payload['post'] : [];

        $source_site = isset($identity['source_site']) ? (string) $identity['source_site'] : '';
        $source_post_id = isset($identity['source_post_id']) ? (int) $identity['source_post_id'] : 0;

        if ($source_site === '' || $source_post_id <= 0) {
            throw new \RuntimeException('Missing identity');
        }

        $post_type = isset($post['post_type']) ? (string) $post['post_type'] : 'post';
        $title = isset($post['title']) ? (string) $post['title'] : '';
        $content = isset($post['content']) ? (string) $post['content'] : '';
        $excerpt = isset($post['excerpt']) ? (string) $post['excerpt'] : '';
        $slug = isset($post['slug']) ? (string) $post['slug'] : '';
        $status = isset($post['status']) ? (string) $post['status'] : 'publish';

        if ($title === '' && $content === '') {
            throw new \RuntimeException('Empty post');
        }

        // Find existing post by meta identity.
        $existing_id = $this->find_existing($post_type, $source_site, $source_post_id);

        $arr = [
            'post_type' => $post_type,
            'post_title' => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status' => $status,
        ];
        if ($slug !== '') {
            $arr['post_name'] = $slug;
        }

        if ($existing_id > 0) {
            $arr['ID'] = $existing_id;
            $new_id = wp_update_post(wp_slash($arr), true);
        } else {
            $new_id = wp_insert_post(wp_slash($arr), true);
        }

        if (is_wp_error($new_id)) {
            throw new \RuntimeException('WP insert/update failed: ' . $new_id->get_error_message());
        }

        $post_id = (int) $new_id;

        // Save identity meta
        update_post_meta($post_id, '_wpnexus_source_site', $source_site);
        update_post_meta($post_id, '_wpnexus_source_post_id', $source_post_id);

        // Taxonomies
        if (isset($post['taxonomies']) && is_array($post['taxonomies'])) {
            $this->apply_taxonomies($post_id, $post['taxonomies']);
        }

        // SEO
        if (isset($post['seo']) && is_array($post['seo'])) {
            foreach ($post['seo'] as $k => $v) {
                if (!is_string($k) || $k === '') { continue; }
                update_post_meta($post_id, $k, $v);
            }
        }

        // Meta
        if (isset($post['meta']) && is_array($post['meta'])) {
            foreach ($post['meta'] as $k => $v) {
                if (!is_string($k) || $k === '') { continue; }
                update_post_meta($post_id, $k, $v);
            }
        }
        // ACF (same as meta)
        if (isset($post['acf']) && is_array($post['acf'])) {
            foreach ($post['acf'] as $k => $v) {
                if (!is_string($k) || $k === '') { continue; }
                update_post_meta($post_id, $k, $v);
            }
        }

        // Content images: download and replace URLs
        if (isset($post['content_images']) && is_array($post['content_images'])) {
            $content = $this->replace_content_images($post_id, $content, $post['content_images']);
        }

        // Featured image
        if (isset($post['featured_image']) && is_array($post['featured_image'])) {
            $thumb_id = $this->handle_featured_image($post_id, $post['featured_image']);
            if ($thumb_id > 0) {
                set_post_thumbnail($post_id, $thumb_id);
            }
        }

        // Update content again if replaced
        if ($content !== (string) get_post_field('post_content', $post_id)) {
            wp_update_post(['ID' => $post_id, 'post_content' => $content]);
        }

        return [
            'post_id' => $post_id,
            'updated' => ($existing_id > 0),
        ];
    }

    private function find_existing(string $post_type, string $source_site, int $source_post_id): int {
        $q = new \WP_Query([
            'post_type' => $post_type,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                ['key' => '_wpnexus_source_site', 'value' => $source_site],
                ['key' => '_wpnexus_source_post_id', 'value' => (string) $source_post_id],
            ],
        ]);

        if ($q->have_posts()) {
            return (int) $q->posts[0];
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $tax_payload
     */
    private function apply_taxonomies(int $post_id, array $tax_payload): void {
        foreach ($tax_payload as $taxonomy => $terms) {
            if (!is_string($taxonomy) || $taxonomy === '' || !taxonomy_exists($taxonomy)) { continue; }
            if (!is_array($terms) || !$terms) { continue; }

            $term_ids = [];
            foreach ($terms as $t) {
                if (!is_array($t)) { continue; }
                $name = isset($t['name']) ? (string) $t['name'] : '';
                $slug = isset($t['slug']) ? (string) $t['slug'] : '';
                if ($name === '') { continue; }

                $tid = 0;

                // Try by slug then by name.
                if ($slug !== '') {
                    $found = get_term_by('slug', $slug, $taxonomy);
                    if ($found && !is_wp_error($found)) {
                        $tid = (int) $found->term_id;
                    }
                }
                if ($tid <= 0) {
                    $found = get_term_by('name', $name, $taxonomy);
                    if ($found && !is_wp_error($found)) {
                        $tid = (int) $found->term_id;
                    }
                }

                if ($tid <= 0) {
                    $res = wp_insert_term($name, $taxonomy, $slug !== '' ? ['slug' => $slug] : []);
                    if (!is_wp_error($res) && is_array($res) && isset($res['term_id'])) {
                        $tid = (int) $res['term_id'];
                    }
                }

                if ($tid > 0) { $term_ids[] = $tid; }
            }

            if ($term_ids) {
                wp_set_object_terms($post_id, $term_ids, $taxonomy, false);
            }
        }
    }

    /**
     * @param array<int, mixed> $urls
     */
    private function replace_content_images(int $post_id, string $content, array $urls): string {
        $limit = 10;
        $count = 0;

        foreach ($urls as $u) {
            if ($count >= $limit) { break; }
            $url = is_string($u) ? $u : '';
            $url = trim($url);
            if ($url === '' || stripos($url, 'http') !== 0) { continue; }

            $att_id = $this->media->sideload_url($url, $post_id, 'WPNexus content image');
            if ($att_id > 0) {
                $new_url = wp_get_attachment_url($att_id);
                if ($new_url) {
                    $content = str_replace($url, (string) $new_url, $content);
                }
            }

            $count++;
        }

        return $content;
    }

    /**
     * @param array<string, mixed> $feat
     */
    private function handle_featured_image(int $post_id, array $feat): int {
        $mode = isset($feat['mode']) ? (string) $feat['mode'] : '';
        if ($mode === 'b64') {
            $b64 = isset($feat['data_b64']) ? (string) $feat['data_b64'] : '';
            $filename = isset($feat['filename']) ? (string) $feat['filename'] : ('featured-' . $post_id . '.png');
            $mime = isset($feat['mime']) ? (string) $feat['mime'] : 'image/png';
            if ($b64 === '') { return 0; }
            return $this->media->sideload_b64($b64, $filename, $mime, $post_id, 'WPNexus featured image');
        }
        if ($mode === 'url') {
            $url = isset($feat['url']) ? (string) $feat['url'] : '';
            if ($url === '') { return 0; }
            return $this->media->sideload_url($url, $post_id, 'WPNexus featured image');
        }
        return 0;
    }
}
