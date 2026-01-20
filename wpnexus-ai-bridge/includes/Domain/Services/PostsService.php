<?php
namespace WPNexusAIBridge\Domain\Services;

use WP_Error;
use WPNexusAIBridge\Logging\Logger;

if (!defined('ABSPATH')) {
	exit;
}

final class PostsService {

	/** @var Logger */
	private $logger;

	/** @var LanguageAssignmentService */
	private $lang;

	public function __construct() {
		$this->logger = Logger::instance();
		$this->lang   = new LanguageAssignmentService();
	}

	public function upsert(array $payload) {
		$post_type = isset($payload['post_type']) ? sanitize_key((string) $payload['post_type']) : '';
		if ($post_type === '' || !post_type_exists($post_type)) {
			return new WP_Error('wpnexus_bridge_post_type_invalid', t('rest_post_type_invalid'), ['status' => 400]);
		}

		$existing_id = 0;
		if (!empty($payload['remote_post_id'])) {
			$existing_id = (int) $payload['remote_post_id'];
		} elseif (!empty($payload['signature'])) {
			$existing_id = (int) $this->find_post_by_signature((string) $payload['signature'], $post_type);
		}

		if ($existing_id > 0) {
			$p = get_post($existing_id);
			if (!$p || $p->post_type !== $post_type) {
				return new WP_Error('wpnexus_bridge_post_not_found', t('rest_post_not_found'), ['status' => 404]);
			}
			if (!current_user_can('edit_post', $existing_id)) {
				return new WP_Error('wpnexus_bridge_forbidden', t('rest_forbidden'), ['status' => 403]);
			}
		}

		$status = isset($payload['status']) ? sanitize_key((string) $payload['status']) : 'draft';
		$allowed_status = ['publish','draft','pending','private','future'];
		if (!in_array($status, $allowed_status, true)) {
			$status = 'draft';
		}

		$title   = isset($payload['title']) ? sanitize_text_field((string) $payload['title']) : '';
		$content = isset($payload['content']) ? (string) $payload['content'] : '';
		$excerpt = isset($payload['excerpt']) ? (string) $payload['excerpt'] : '';
		$slug    = isset($payload['slug']) ? sanitize_title((string) $payload['slug']) : '';

		$postarr = [
			'post_type'    => $post_type,
			'post_title'   => $title,
			'post_content' => wp_kses_post($content),
			'post_excerpt' => sanitize_textarea_field($excerpt),
			'post_status'  => $status,
		];

		if ($slug !== '') {
			$postarr['post_name'] = $slug;
		}

		$action = 'updated';

		if ($existing_id > 0) {
			$postarr['ID'] = $existing_id;
			$updated = wp_update_post($postarr, true);
			if (is_wp_error($updated)) {
				return $updated;
			}
			$post_id = (int) $updated;
		} else {
			if (!current_user_can(get_post_type_object($post_type)->cap->create_posts)) {
				return new WP_Error('wpnexus_bridge_forbidden', t('rest_forbidden'), ['status' => 403]);
			}
			$inserted = wp_insert_post($postarr, true);
			if (is_wp_error($inserted)) {
				return $inserted;
			}
			$post_id = (int) $inserted;
			$action  = 'created';
		}

		// Signature
		if (!empty($payload['signature'])) {
			update_post_meta($post_id, '_wpnexus_signature', sanitize_text_field((string) $payload['signature']));
		}

		// Meta
		if (!empty($payload['meta']) && is_array($payload['meta'])) {
			$this->apply_meta($post_id, $payload['meta']);
		}

		// SEO apply (T15)
		if (!empty($payload['seo']) && is_array($payload['seo'])) {
			try {
				$seo = new \WPNexusAIBridge\Domain\Services\SeoService();
				$seo->apply((int) $post_id, (array) $payload['seo'], (array) $payload);
			} catch (\Throwable $e) {
				// Never fatal the sync because of SEO
				$this->logger->warning('posts.seo.apply.fail', [
					'post_id' => (int) $post_id,
					'error'   => $e->getMessage(),
				]);
			}
		}

		// Terms
		if (!empty($payload['terms']) && is_array($payload['terms'])) {
			$this->apply_terms($post_id, $payload['terms']);
		}

		// Featured image
		$featured_id = 0;
		if (!empty($payload['featured_image']) && is_array($payload['featured_image']) && !empty($payload['featured_image']['attachment_id'])) {
			$featured_id = (int) $payload['featured_image']['attachment_id'];
		} elseif (!empty($payload['featured_image_id'])) {
			$featured_id = (int) $payload['featured_image_id'];
		}
		if ($featured_id > 0 && current_user_can('edit_post', $post_id)) {
			set_post_thumbnail($post_id, $featured_id);
		}

		// Language assign (best-effort)
		if (!empty($payload['language_code'])) {
			$this->lang->assign_post_language($post_id, (string) $payload['language_code']);
		}

		$url = get_permalink($post_id);

		return [
			'remote_post_id' => $post_id,
			'url'            => $url ? $url : '',
			'action'         => $action,
		];
	}

	public function delete(array $payload) {
		$post_id = 0;

		if (!empty($payload['remote_post_id'])) {
			$post_id = (int) $payload['remote_post_id'];
		} elseif (!empty($payload['signature'])) {
			$post_id = (int) $this->find_post_by_signature((string) $payload['signature'], '');
			if ($post_id <= 0) {
				return new WP_Error('wpnexus_bridge_signature_not_found', t('rest_signature_not_found'), ['status' => 404]);
			}
		}

		if ($post_id <= 0) {
			return new WP_Error('wpnexus_bridge_invalid_params', t('rest_invalid_params'), ['status' => 400]);
		}

		$p = get_post($post_id);
		if (!$p) {
			return new WP_Error('wpnexus_bridge_post_not_found', t('rest_post_not_found'), ['status' => 404]);
		}

		if (!current_user_can('delete_post', $post_id)) {
			return new WP_Error('wpnexus_bridge_forbidden', t('rest_forbidden'), ['status' => 403]);
		}

		$mode = isset($payload['mode']) ? sanitize_key((string) $payload['mode']) : 'trash';
		$force = ($mode === 'delete');

		$deleted = wp_delete_post($post_id, $force);
		if (!$deleted) {
			return new WP_Error('wpnexus_bridge_delete_failed', t('rest_delete_failed'), ['status' => 500]);
		}

		return [
			'remote_post_id' => $post_id,
			'deleted'        => true,
			'mode'           => $mode,
		];
	}

	private function find_post_by_signature(string $signature, string $post_type = ''): int {
		$signature = trim($signature);
		if ($signature === '') {
			return 0;
		}

		$args = [
			'post_type'      => $post_type !== '' ? $post_type : 'any',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'meta_query'     => [
				[
					'key'   => '_wpnexus_signature',
					'value' => $signature,
				],
			],
		];

		$q = new \WP_Query($args);
		if (!empty($q->posts[0])) {
			return (int) $q->posts[0];
		}
		return 0;
	}

	private function apply_meta(int $post_id, array $meta): void {
		foreach ($meta as $k => $v) {
			$key = (string) $k;
			if (!preg_match('/^[A-Za-z0-9_\-:]{1,128}$/', $key)) {
				continue;
			}

			// Allow scalar or JSON-ish
			if (is_array($v) || is_object($v)) {
				$v = wp_json_encode($v);
			}

			if ($v === null) {
				delete_post_meta($post_id, $key);
				continue;
			}

			update_post_meta($post_id, $key, is_string($v) ? $v : (string) $v);
		}
	}

	private function apply_terms(int $post_id, array $terms_by_tax): void {
		foreach ($terms_by_tax as $taxonomy => $items) {
			$tax = sanitize_key((string) $taxonomy);
			if ($tax === '' || !taxonomy_exists($tax)) {
				continue;
			}

			$values = [];
			foreach ((array) $items as $item) {
				if (is_int($item) || (is_string($item) && ctype_digit($item))) {
					$values[] = (int) $item;
				} elseif (is_string($item)) {
					$values[] = sanitize_text_field($item);
				} elseif (is_array($item)) {
					if (!empty($item['id'])) {
						$values[] = (int) $item['id'];
					} elseif (!empty($item['slug'])) {
						$values[] = sanitize_text_field((string) $item['slug']);
					} elseif (!empty($item['name'])) {
						$values[] = sanitize_text_field((string) $item['name']);
					}
				}
			}

			if (!empty($values)) {
				wp_set_object_terms($post_id, $values, $tax, false);
			}
		}
	}
}
