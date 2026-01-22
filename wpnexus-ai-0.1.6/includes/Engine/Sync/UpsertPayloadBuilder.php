<?php
namespace WPNexusAI\Engine\Sync;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Builds the payload for Bridge /posts/upsert from extracted data + target config.
 *
 * IMPORTANT:
 * - UpsertTask currently calls build($extracted, $target_row, $lang, $job_payload, $registry_row)
 * - Older code may call build($extracted, $target_row, $job_payload, $registry_row)
 *
 * This class supports both call signatures for backwards compatibility.
 */
final class UpsertPayloadBuilder {

	/**
	 * Build payload for Bridge /posts/upsert.
	 *
	 * @param array<string,mixed> $extracted
	 * @param array<string,mixed> $target_row
	 * @param mixed $arg3
	 * @param mixed|null $arg4
	 * @param mixed|null $arg5
	 * @return array<string,mixed>
	 */
	public function build(array $extracted, array $target_row, $arg3, $arg4 = null, $arg5 = null): array {
		$language_code = 'auto';
		$job_payload   = [];
		$registry_row  = null;

		// Signature A: ($lang, $payload, $registry)
		if (is_string($arg3)) {
			$language_code = sanitize_key($arg3);
			$job_payload   = is_array($arg4) ? $arg4 : [];
			$registry_row  = is_array($arg5) ? $arg5 : null;
		} else {
			// Signature B: ($payload, $registry)
			$job_payload  = is_array($arg3) ? $arg3 : [];
			$registry_row = is_array($arg4) ? $arg4 : null;

			if (isset($job_payload['language_code']) && is_string($job_payload['language_code'])) {
				$language_code = sanitize_key((string) $job_payload['language_code']);
			}
		}

		$source_post_id = 0;
		if (isset($extracted['post_id'])) {
			$source_post_id = (int) $extracted['post_id'];
		} elseif (isset($job_payload['source_post_id'])) {
			$source_post_id = (int) $job_payload['source_post_id'];
		} elseif (isset($job_payload['post_id'])) {
			$source_post_id = (int) $job_payload['post_id'];
		}

		$target_id = isset($target_row['id']) ? (int) $target_row['id'] : (isset($job_payload['target_id']) ? (int) $job_payload['target_id'] : 0);

		if ($language_code === '') {
			$language_code = 'auto';
		}

		// Normalized extracted post data (Extractor uses nested ['post'] keys).
		$post = [];
		if (isset($extracted['post']) && is_array($extracted['post'])) {
			$post = $extracted['post'];
		}

		$post_type = '';
		if (isset($post['post_type']) && is_string($post['post_type'])) {
			$post_type = sanitize_key((string) $post['post_type']);
		} elseif (isset($extracted['post_type']) && is_string($extracted['post_type'])) {
			$post_type = sanitize_key((string) $extracted['post_type']);
		}
		if ($post_type === '') {
			$post_type = 'post';
		}

		// Status: prefer target default if provided, else extracted status, else draft.
		$status = '';
		if (isset($post['status']) && is_string($post['status'])) {
			$status = sanitize_key((string) $post['status']);
		} elseif (isset($extracted['status']) && is_string($extracted['status'])) {
			$status = sanitize_key((string) $extracted['status']);
		}
		$default_status = isset($target_row['status_default']) ? sanitize_key((string) $target_row['status_default']) : '';

		$status = $default_status !== '' ? $default_status : $status;
		if ($status === '') {
			$status = 'draft';
		}

		// Allow job override (rare, but safe).
		if (isset($job_payload['status']) && is_string($job_payload['status']) && $job_payload['status'] !== '') {
			$status = sanitize_key($job_payload['status']);
		}

		$title   = '';
		$excerpt = '';
		$content = '';

		if (isset($post['title']) && is_string($post['title'])) {
			$title = (string) $post['title'];
		} elseif (isset($extracted['title']) && is_string($extracted['title'])) {
			$title = (string) $extracted['title'];
		}

		if (isset($post['excerpt']) && is_string($post['excerpt'])) {
			$excerpt = (string) $post['excerpt'];
		} elseif (isset($extracted['excerpt']) && is_string($extracted['excerpt'])) {
			$excerpt = (string) $extracted['excerpt'];
		}

		if (isset($post['content']) && is_string($post['content'])) {
			$content = (string) $post['content'];
		} elseif (isset($extracted['content']) && is_string($extracted['content'])) {
			$content = (string) $extracted['content'];
		}

		$slug = '';
		if (isset($post['slug']) && is_string($post['slug'])) {
			$slug = sanitize_title((string) $post['slug']);
		} elseif (isset($extracted['slug']) && is_string($extracted['slug'])) {
			$slug = sanitize_title((string) $extracted['slug']);
		}
		if ($slug === '' && $title !== '') {
			$slug = sanitize_title($title);
		}

		$meta = [];
		if (isset($extracted['meta']) && is_array($extracted['meta'])) {
			$meta = $extracted['meta'];
		}

		// Registry link => update existing remote post_id if available.
		$remote_post_id = '';
		$remote_url     = '';
		if (is_array($registry_row)) {
			if (isset($registry_row['remote_post_id']) && is_string($registry_row['remote_post_id'])) {
				$remote_post_id = (string) $registry_row['remote_post_id'];
			}
			if (isset($registry_row['url']) && is_string($registry_row['url'])) {
				$remote_url = (string) $registry_row['url'];
			}
		}

		// Build signature for idempotency on target.
		$signature = [
			'source_site' => site_url(),
			'post_id'     => $source_post_id,
			'target_id'   => $target_id,
			'lang'        => $language_code,
		];

		// Terms (already mapped upstream by TermsMapper where possible).
		$terms_payload = [];
		if (isset($job_payload['terms']) && is_array($job_payload['terms'])) {
			$terms_payload = $job_payload['terms'];
		} elseif (isset($extracted['terms']) && is_array($extracted['terms'])) {
			$terms_payload = $extracted['terms'];
		}

		// Featured image descriptor (media upload/attach is handled elsewhere).
		$featured = [];
		if (isset($extracted['featured_image']) && is_array($extracted['featured_image'])) {
			$fi = $extracted['featured_image'];
			if (isset($fi['url']) && is_string($fi['url']) && $fi['url'] !== '') {
				$featured = [
					'url'  => esc_url_raw($fi['url']),
					'alt'  => isset($fi['alt']) ? sanitize_text_field((string) $fi['alt']) : '',
					'mime' => isset($fi['mime']) ? sanitize_text_field((string) $fi['mime']) : '',
				];
			}
		}

		$payload = [
			'post_type'     => $post_type,
			'title'         => $title,
			'content'       => $content,
			'excerpt'       => $excerpt,
			'status'        => $status,
			'slug'          => $slug,
			'language_code' => $language_code,
			'meta'          => $meta,
			'terms'         => $terms_payload,
			'signature'     => $signature,
		];

		if ($remote_post_id !== '') {
			$payload['remote_post_id'] = $remote_post_id;
		}
		if ($remote_url !== '') {
			$payload['url'] = $remote_url;
		}

		if (!empty($featured)) {
			$payload['featured_image'] = $featured;
		}

		// If MediaSync uploaded a featured image, pass its attachment_id for reliable assignment.
		if (!empty($job_payload['featured_image_id'])) {
			$fid = (int) $job_payload['featured_image_id'];
			if ($fid > 0) {
				$payload['featured_image_id'] = $fid;
				if (!empty($payload['featured_image']) && is_array($payload['featured_image'])) {
					$payload['featured_image']['attachment_id'] = $fid;
				} else {
					$payload['featured_image'] = ['attachment_id' => $fid];
				}
			}
		}

		// Allow job-level overrides (advanced).
		if (isset($job_payload['post_type']) && is_string($job_payload['post_type']) && $job_payload['post_type'] !== '') {
			$payload['post_type'] = sanitize_key($job_payload['post_type']);
		}
		if (isset($job_payload['title']) && is_string($job_payload['title'])) {
			$payload['title'] = (string) $job_payload['title'];
		}
		if (isset($job_payload['content']) && is_string($job_payload['content'])) {
			$payload['content'] = (string) $job_payload['content'];
		}
		if (isset($job_payload['excerpt']) && is_string($job_payload['excerpt'])) {
			$payload['excerpt'] = (string) $job_payload['excerpt'];
		}
		if (isset($job_payload['slug']) && is_string($job_payload['slug']) && $job_payload['slug'] !== '') {
			$payload['slug'] = sanitize_title($job_payload['slug']);
		}

		return $payload;
	}

	/**
	 * Extract a stable subset of an upsert payload for idempotent hashing.
	 *
	 * The content hash should reflect "what we intend to write", not remote identifiers.
	 *
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	public function hashable(array $payload): array {
		// Remove remote identifiers / volatile fields.
		unset($payload['remote_post_id'], $payload['url']);

		// Normalize terms to stable structure (prefer term_id).
		if (isset($payload['terms']) && is_array($payload['terms'])) {
			$payload['terms'] = $this->normalize_terms_for_hash($payload['terms']);
		}

		// Ensure deterministic ordering for associative arrays.
		return $this->normalize_for_hash($payload);
	}

	/**
	 * @param array<string,mixed> $terms
	 * @return array<string,array<int,mixed>>
	 */
	private function normalize_terms_for_hash(array $terms): array {
		$out = [];

		foreach ($terms as $tax => $items) {
			$tax = sanitize_key((string) $tax);
			if ($tax === '' || !is_array($items)) {
				continue;
			}

			$bucket = [];
			foreach ($items as $it) {
				if (!is_array($it)) {
					continue;
				}
				// Prefer numeric term_id if present; else fall back to slug/name.
				if (isset($it['term_id']) && (int) $it['term_id'] > 0) {
					$bucket[] = (int) $it['term_id'];
					continue;
				}
				if (isset($it['id']) && (int) $it['id'] > 0) {
					$bucket[] = (int) $it['id'];
					continue;
				}
				if (isset($it['slug']) && is_string($it['slug']) && $it['slug'] !== '') {
					$bucket[] = sanitize_title($it['slug']);
					continue;
				}
				if (isset($it['name']) && is_string($it['name']) && $it['name'] !== '') {
					$bucket[] = sanitize_text_field($it['name']);
					continue;
				}
			}

			// De-dup + sort for stability.
			$bucket = array_values(array_unique($bucket, SORT_REGULAR));
			sort($bucket);

			$out[$tax] = $bucket;
		}

		ksort($out);
		return $out;
	}

	/**
	 * Recursively normalize arrays for stable hashing:
	 * - associative arrays: ksort keys
	 * - numeric arrays: normalize children then sort scalars where safe
	 *
	 * @param mixed $data
	 * @return mixed
	 */
	private function normalize_for_hash($data) {
		if (!is_array($data)) {
			return $data;
		}

		// Determine if associative.
		$is_assoc = array_keys($data) !== range(0, count($data) - 1);

		if ($is_assoc) {
			$out = [];
			foreach ($data as $k => $v) {
				$out[(string) $k] = $this->normalize_for_hash($v);
			}
			ksort($out);
			return $out;
		}

		// Numeric list.
		$out = [];
		foreach ($data as $v) {
			$out[] = $this->normalize_for_hash($v);
		}

		// If all scalars, sort for deterministic ordering.
		$all_scalar = true;
		foreach ($out as $v) {
			if (is_array($v) || is_object($v)) {
				$all_scalar = false;
				break;
			}
		}
		if ($all_scalar) {
			sort($out);
		}

		return $out;
	}
}

