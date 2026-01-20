<?php
namespace WPNexusAI\Engine\Sync;

use WPNexusAI\SEO\SeoExtractor;

if (!defined('ABSPATH')) {
	exit;
}

final class UpsertPayloadBuilder {

	/**
	 * @param array<string,mixed> $extracted
	 * @param array<string,mixed> $target_row
	 * @param array<string,mixed> $job_payload
	 * @param array<string,mixed>|null $registry_row
	 * @return array<string,mixed>
	 */
	public function build(array $extracted, array $target_row, array $job_payload, ?array $registry_row): array {
		$post = isset($extracted['post']) && is_array($extracted['post']) ? $extracted['post'] : [];

		$status_default = isset($target_row['status_default']) ? sanitize_key((string) $target_row['status_default']) : 'draft';
		if (!in_array($status_default, ['publish','draft','pending','private'], true)) {
			$status_default = 'draft';
		}

		$language_code = '';
		if (!empty($job_payload['language_code'])) {
			$language_code = sanitize_key((string) $job_payload['language_code']);
		} elseif (!empty($job_payload['target_lang'])) {
			$language_code = sanitize_key((string) $job_payload['target_lang']);
		}

		$signature = Signature::make((int) ($job_payload['source_post_id'] ?? 0), (int) ($target_row['id'] ?? 0), $language_code);

		$out = [
			'post_type'     => isset($post['post_type']) ? sanitize_key((string) $post['post_type']) : 'post',
			'title'         => isset($post['title']) ? (string) $post['title'] : '',
			'content'       => isset($post['content']) ? (string) $post['content'] : '',
			'excerpt'       => isset($post['excerpt']) ? (string) $post['excerpt'] : '',
			'status'        => $status_default,
			'slug'          => isset($post['slug']) ? sanitize_title((string) $post['slug']) : '',
			'meta'          => isset($post['meta']) && is_array($post['meta']) ? $post['meta'] : [],
			'terms'         => isset($extracted['terms']) && is_array($extracted['terms']) ? $extracted['terms'] : [],
			'language_code' => $language_code,
			'signature'     => $signature,
			'seo'           => $this->build_seo((int) ($job_payload['source_post_id'] ?? 0), $target_row),
		];

		if (is_array($registry_row) && !empty($registry_row['remote_post_id'])) {
			$out['remote_post_id'] = (int) $registry_row['remote_post_id'];
		}

		/**
		 * Last chance to customize payload before Bridge.
		 *
		 * @param array<string,mixed> $out
		 */
		return apply_filters('wpnexus_ai_upsert_payload', $out, $extracted, $target_row, $job_payload, $registry_row);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function build_seo(int $source_post_id, array $target_row): array {
		$source_post_id = (int) $source_post_id;

		$seo = (new SeoExtractor())->extract($source_post_id);

		// Canonical defaults (target config)
		$mode = isset($target_row['seo_canonical_mode']) ? sanitize_key((string) $target_row['seo_canonical_mode']) : 'self';
		if (!in_array($mode, ['self','source','custom'], true)) {
			$mode = 'self';
		}

		$custom = isset($target_row['seo_canonical_custom']) ? esc_url_raw((string) $target_row['seo_canonical_custom']) : '';

		$seo['canonical_mode']   = $mode;
		$seo['canonical_custom'] = $custom;

		return $seo;
	}
}

