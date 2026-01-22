<?php
namespace WPNexusAI\Services;

use WPNexusAI\Bridge\BridgeClient;
use WPNexusAI\Extraction\PostExtractor;
use WPNexusAI\Logging\Logger;
use WPNexusAI\Rules\RulesRepo;
use WPNexusAI\Targets\TargetRepo;

if (!defined('ABSPATH')) { exit; }

final class UpsertService {

    /** @var Logger */
    private $logger;

    /** @var RulesRepo */
    private $rules;

    /** @var TargetRepo */
    private $targets;

    /** @var BridgeClient */
    private $bridge;

    /** @var PostExtractor */
    private $extractor;

    /** @var InternalLinker */
    private $linker;

    /** @var MediaService */
    private $media;

    public function __construct() {
        $this->logger = Logger::instance();
        $this->rules = new RulesRepo();
        $this->targets = new TargetRepo();
        $this->bridge = new BridgeClient();
        $this->extractor = new PostExtractor();
        $this->linker = new InternalLinker();
        $this->media = new MediaService();
    }

    /**
     * @param int $job_id
     * @param array<string, mixed> $payload
     */
    public function handle_job(int $job_id, array $payload): void {
        $post_id = (int) ($payload['post_id'] ?? 0);
        $rule_id = (int) ($payload['rule_id'] ?? 0);
        $target_id = (int) ($payload['target_id'] ?? 0);

        if ($post_id <= 0 || $rule_id <= 0 || $target_id <= 0) {
            throw new \RuntimeException('Invalid job payload');
        }

        $rule = $this->rules->get($rule_id);
        if (!$rule) {
            throw new \RuntimeException('Rule not found: ' . $rule_id);
        }
        $target_row = $this->targets->get($target_id);
        if (!$target_row) {
            throw new \RuntimeException('Target not found: ' . $target_id);
        }

        $target_settings = $this->targets->settings($target_row);
        $target = [
            'id' => $target_id,
            'name' => (string) $target_row['name'],
            'base_url' => (string) $target_row['base_url'],
            'target_lang' => (string) ($target_settings['lang'] ?? ''),
        ];

        $token = $this->targets->token_plain($target_row);
        if ($token === '') {
            throw new \RuntimeException('Target token is empty');
        }

        $post_payload = $this->extractor->build_payload($post_id, $rule, $target);
        if (!$post_payload) {
            throw new \RuntimeException('Failed to extract post payload');
        }

        // Apply category mapping (source term id -> target category name).
        $cat_map = $this->rules->category_map($rule);
        if ($cat_map) {
            foreach (['category', 'product_cat'] as $tax) {
                if (isset($post_payload['taxonomies'][$tax]) && is_array($post_payload['taxonomies'][$tax])) {
                    foreach ($post_payload['taxonomies'][$tax] as &$term) {
                        $src_id = (int) ($term['source_term_id'] ?? 0);
                        if ($src_id > 0 && isset($cat_map[(string) $src_id])) {
                            $mapped = (string) $cat_map[(string) $src_id];
                            if ($mapped !== '') {
                                $term['name'] = $mapped;
                                $term['slug'] = sanitize_title($mapped);
                            }
                        }
                    }
                    unset($term);
                }
            }
        }

        // Internal link injection (affiliate links etc).
        $links = $this->rules->internal_links($rule);
        if ($links && isset($post_payload['content']) && is_string($post_payload['content'])) {
            $post_payload['content'] = $this->linker->apply($post_payload['content'], $links);
        }

        // Media: featured image + content images list
        $feat = $this->media->featured_image_payload($post_id, $rule, (string) ($post_payload['content'] ?? ''));
        $post_payload['featured_image'] = $feat;

        $post_payload['content_images'] = $this->media->extract_content_image_urls((string) ($post_payload['content'] ?? ''));

        $full = [
            'identity' => [
                'source_site' => site_url(),
                'source_post_id' => $post_id,
                'rule_id' => $rule_id,
            ],
            'target' => [
                'target_id' => $target_id,
            ],
            'post' => $post_payload,
        ];

        $this->logger->info('upsert.send.start', ['job_id' => $job_id, 'post_id' => $post_id, 'target_id' => $target_id]);

        $res = $this->bridge->upsert($target['base_url'], $token, $full);

        if (!$res['ok']) {
            $err = 'Bridge HTTP ' . $res['code'];
            if (is_array($res['body']) && isset($res['body']['message'])) {
                $err .= ': ' . (string) $res['body']['message'];
            }
            throw new \RuntimeException($err);
        }

        $this->logger->info('upsert.send.done', ['job_id' => $job_id, 'post_id' => $post_id, 'target_id' => $target_id]);
    }
}
