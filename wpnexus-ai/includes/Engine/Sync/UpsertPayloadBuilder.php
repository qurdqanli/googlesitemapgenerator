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
	 * Supported call signatures:
	 * 1) build($extracted, $target_row, $language_code, $job_payload, $registry_row)
	 * 2) build($extracted, $target_row, $job_payload, $registry_row)
	 *
	 * @param array<string,mixed> $extracted
	 * @param array<string,mixed> $target_row
	 * @param mixed $arg3 string $language_code OR array $job_payload
	 * @param mixed $arg4 array $job_payload OR array|null $registry_row
	 * @param mixed $arg5 array|null $registry_row
	 *
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
			$job_payload   = is_array($arg3) ? $arg3 : [];
			$registry_row  = is_array($arg4) ? $arg4 : null;

			if (isset($job_payload['language_code']) && is_string($job_payload['language_code'])) {
				$language_code = sanitize_key($job_payload['language_code']);
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

		$post_type = isset($extracted['post_type']) ? sanitize_key((string) $extracted['post_type']) : 'post';
		if ($post_type === '') {
			$post_type = 'post';
		}

		// Status: prefer target default if provided, else extracted status, else draft.
		$status = isset($extracted['status']) ? sanitize_key((string) $extracted['status']) : '';
		$default_status = isset($target_row['status_default']) ? sanitize_key((string) $target_row['status_default']) : '';

		$status = $default_status !== '' ? $default_status : $status;
		if ($status === '') {
			$status = 'draft';
		}

		// Allow job override (rare, but safe).
		if (isset($job_payload['status']) && is_string($job_payload['status']) && $job_payload['status'] !== '') {
			$status = sanitize_key($job_payload['status']);
		}

		$title   = isset($extracted['title']) ? (string) $extracted['title'] : '';
		$excerpt = isset($extracted['excerpt']) ? (string) $extracted['excerpt'] : '';
		$content = isset($extracted['content']) ? (string) $extracted['content'] : '';

		$slug = isset($extracted['slug']) ? sanitize_title((string) $extracted['slug']) : '';
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
			if (isset($registry_row['remote_post_id'])) {
				$remote_post_id = (string) $registry_row['remote_post_id'];
			}
			if (isset($registry_row['url'])) {
				$remote_url = (string) $registry_row['url'];
			}
		}

		$signature = '';
		if ($source_post_id > 0 && $target_id > 0) {
			$signature = Signature::make($source_post_id, $target_id, $language_code);
		}

		// Terms are mapped later (TermsMapper) and UpsertTask overwrites when available.
		$terms = [];

		// Featured image support is not wired end-to-end in current Core build.
		// We keep a minimal, non-breaking hint if extractor provided a URL.
		$featured = [];
		if (isset($extracted['featured_image']) && is_array($extracted['featured_image'])) {
			$url = isset($extracted['featured_image']['url']) ? (string) $extracted['featured_image']['url'] : '';
			$mime = isset($extracted['featured_image']['mime']) ? (string) $extracted['featured_image']['mime'] : '';
			if ($url !== '') {
				$featured = [
					'url'  => esc_url_raw($url),
					'mime' => sanitize_text_field($mime),
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
			'meta'          => $meta,
			'terms'         => $terms,
			'language_code' => $language_code,
			'signature'     => $signature,
		];

		// If we know remote_post_id (linked), pass it so Bridge can update directly.
		if ($remote_post_id !== '') {
			$payload['remote_post_id'] = $remote_post_id;
		}

		// Optional: include last known URL (not required, but harmless for debugging).
		if ($remote_url !== '') {
			$payload['remote_url'] = esc_url_raw($remote_url);
		}

		// SEO: keep safe defaults based on target settings.
		$payload['seo'] = $this->build_seo_defaults($target_row);

		// Optional: include featured image hint (only if present).
		if (!empty($featured)) {
			$payload['featured_image'] = $featured;
		}

		return $payload;
	}

	/**
	 * Prepare a stable representation for hashing/idempotency.
	 * Removes volatile fields and normalizes ordering.
	 *
	 * @param array<string,mixed> $post_payload
	 * @return array<string,mixed>
	 */
	public function hashable(array $post_payload): array {
		$h = $post_payload;

		// Remove volatile fields that should not affect content hash.
		unset($h['remote_url']);

		// Normalize meta ordering.
		if (isset($h['meta']) && is_array($h['meta'])) {
			ksort($h['meta']);
		}

		// Normalize seo ordering.
		if (isset($h['seo']) && is_array($h['seo'])) {
			ksort($h['seo']);
		}

		// Normalize terms ordering.
		if (isset($h['terms']) && is_array($h['terms'])) {
			$terms = $h['terms'];
			usort($terms, function ($a, $b) {
				$ta = is_array($a) && isset($a['taxonomy']) ? (string) $a['taxonomy'] : '';
				$tb = is_array($b) && isset($b['taxonomy']) ? (string) $b['taxonomy'] : '';
				if ($ta !== $tb) {
					return strcmp($ta, $tb);
				}
				$ida = is_array($a) && isset($a['term_id']) ? (int) $a['term_id'] : 0;
				$idb = is_array($b) && isset($b['term_id']) ? (int) $b['term_id'] : 0;
				if ($ida !== $idb) {
					return $ida <=> $idb;
				}
				$sa = is_array($a) && isset($a['slug']) ? (string) $a['slug'] : '';
				$sb = is_array($b) && isset($b['slug']) ? (string) $b['slug'] : '';
				return strcmp($sa, $sb);
			});
			$h['terms'] = $terms;
		}

		return $h;
	}

	/**
	 * Build SEO defaults from target settings (safe; does not autoload SeoExtractor).
	 *
	 * @param array<string,mixed> $target_row
	 * @return array<string,mixed>
	 */
	private function build_seo_defaults(array $target_row): array {
		$seo = [];

		// Canonical defaults (target config)
		$mode = isset($target_row['seo_canonical_mode']) ? sanitize_key((string) $target_row['seo_canonical_mode']) : 'self';
		if (!in_array($mode, ['self','source','custom'], true)) {
			$mode = 'self';
		}

		$custom = isset($target_row['seo_canonical_custom']) ? esc_url_raw((string) $target_row['seo_canonical_custom']) : '';

		$seo['canonical_mode']   = $mode;
		$seo['canonical_custom'] = $custom;

		// hreflang default is "auto" in spec; Bridge may ignore if not supported yet.
		$seo['hreflang'] = 'auto';

		return $seo;
	}
}

