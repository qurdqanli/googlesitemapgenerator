<?php
namespace WPNexusAI\Engine\Extract;

use WP_Error;
use WP_Post;
use WPNexusAI\Logging\Logger;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Extract post data from the source site for translation/sync.
 *
 * NOTE: Heavy work (media upload, remote API) must be queued elsewhere.
 * This extractor is purely local and fast.
 */
final class Extractor {

	/** @var Logger */
	private $logger;

	public function __construct() {
		$this->logger = Logger::instance();
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function extract(int $post_id) {
		$post_id = (int) $post_id;

		$this->logger->info('engine.extract.start', [
			'post_id' => $post_id,
		]);

		if ($post_id <= 0) {
			return new WP_Error('wpnexus_ai_invalid_post_id', 'Invalid post_id.');
		}

		$post = get_post($post_id);
		if (!($post instanceof WP_Post)) {
			return new WP_Error('wpnexus_ai_post_not_found', 'Post not found.');
		}

		// Core fields
		$data = [
			'source' => [
				'site_url' => site_url(),
				'post_id'  => $post_id,
			],
			'post' => [
				'post_type'    => (string) $post->post_type,
				'status'       => (string) $post->post_status,
				'slug'         => (string) $post->post_name,
				'author_id'    => (int) $post->post_author,
				'title'        => html_entity_decode(get_the_title($post_id), ENT_QUOTES, get_bloginfo('charset')),
				'content'      => (string) $post->post_content,
				'excerpt'      => (string) $post->post_excerpt,
				'date_gmt'     => (string) $post->post_date_gmt,
				'modified_gmt' => (string) $post->post_modified_gmt,
			],
			'terms'          => $this->extract_terms($post),
			'featured_image' => $this->extract_featured_image($post_id),
			'meta'           => $this->extract_meta($post_id),
		];

		/**
		 * Allow last-second normalization/extension.
		 *
		 * @param array<string,mixed> $data
		 * @param WP_Post $post
		 */
		$data = apply_filters('wpnexus_ai_extractor_data', $data, $post);

		$this->logger->info('engine.extract.done', [
			'post_id'      => $post_id,
			'post_type'    => (string) $post->post_type,
			'terms_count'  => is_array($data['terms'] ?? null) ? count((array) $data['terms']) : 0,
			'has_featured' => !empty($data['featured_image']['attachment_id']),
		]);

		return $data;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function extract_terms(WP_Post $post): array {
		$out = [];

		$taxonomies = get_object_taxonomies($post->post_type, 'names');
		if (!is_array($taxonomies)) {
			return $out;
		}

		foreach ($taxonomies as $taxonomy) {
			$taxonomy = sanitize_key((string) $taxonomy);
			if ($taxonomy === '') {
				continue;
			}

			$terms = get_the_terms($post, $taxonomy);
			if (is_wp_error($terms) || empty($terms) || !is_array($terms)) {
				continue;
			}

			foreach ($terms as $t) {
				$out[] = [
					'taxonomy' => $taxonomy,
					'term_id'  => (int) $t->term_id,
					'slug'     => (string) $t->slug,
					'name'     => (string) $t->name,
					'parent'   => (int) $t->parent,
				];
			}
		}

		return $out;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function extract_featured_image(int $post_id): array {
		$attachment_id = (int) get_post_thumbnail_id($post_id);
		if ($attachment_id <= 0) {
			return [
				'attachment_id' => 0,
				'url'           => '',
				'file'          => '',
				'mime'          => '',
			];
		}

		$url  = (string) wp_get_attachment_url($attachment_id);
		$file = (string) get_attached_file($attachment_id);
		$mime = (string) get_post_mime_type($attachment_id);

		return [
			'attachment_id' => $attachment_id,
			'url'           => $url,
			'file'          => $file,
			'mime'          => $mime,
		];
	}

	/**
	 * Extract a safe subset of post meta (opt-in via filter).
	 *
	 * @return array<string,mixed>
	 */
	private function extract_meta(int $post_id): array {
		$keys = apply_filters('wpnexus_ai_extractor_meta_keys', [], $post_id);
		if (!is_array($keys) || empty($keys)) {
			return [];
		}

		$out = [];
		foreach ($keys as $key) {
			$key = sanitize_key((string) $key);
			if ($key === '') {
				continue;
			}
			$out[$key] = get_post_meta($post_id, $key, true);
		}

		/**
		 * @param array<string,mixed> $out
		 */
		return apply_filters('wpnexus_ai_extractor_meta', $out, $post_id);
	}
}
