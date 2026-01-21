<?php
namespace WPNexusAI\SEO;

use WPNexusAI\Logging\Logger;

if (!defined('ABSPATH')) {
	exit;
}

final class SeoExtractor {

	/** @var Logger */
	private $logger;

	public function __construct() {
		$this->logger = Logger::instance();
	}

	/**
	 * Returns normalized SEO payload extracted from supported plugins.
	 *
	 * @return array<string,mixed>
	 */
	public function extract(int $post_id): array {
		$post_id = (int) $post_id;

		$adapter = $this->detect_adapter();

		$payload = [
			'source'      => $adapter,
			'title'       => '',
			'description' => '',
			'focus_keyword'=> '',
			'canonical'   => '',
			'robots'      => ['index' => null, 'follow' => null],
			'og'          => ['title' => '', 'description' => '', 'image_id' => 0, 'image_url' => ''],
			'twitter'     => ['title' => '', 'description' => '', 'image_id' => 0, 'image_url' => ''],
			'raw'         => [],
		];

		if ($adapter === 'yoast') {
			$payload = $this->extract_yoast($post_id, $payload);
		} elseif ($adapter === 'rankmath') {
			$payload = $this->extract_rankmath($post_id, $payload);
		} elseif ($adapter === 'seopress') {
			$payload = $this->extract_seopress($post_id, $payload);
		} else {
			$this->logger->debug('seo.extract.none', ['post_id' => $post_id]);
		}

		$payload = apply_filters('wpnexus_ai_seo_payload', $payload, $post_id);

		$this->logger->info('seo.extract.done', [
			'post_id' => $post_id,
			'source'  => (string) ($payload['source'] ?? 'none'),
			'has_t'   => !empty($payload['title']) ? 1 : 0,
			'has_d'   => !empty($payload['description']) ? 1 : 0,
		]);

		return is_array($payload) ? $payload : [];
	}

	private function detect_adapter(): string {
		// Priority can be overridden.
		$order = apply_filters('wpnexus_ai_seo_adapter_order', ['yoast','rankmath','seopress']);
		if (!is_array($order) || empty($order)) {
			$order = ['yoast','rankmath','seopress'];
		}

		foreach ($order as $id) {
			$id = sanitize_key((string) $id);

			if ($id === 'yoast' && (defined('WPSEO_VERSION') || class_exists('WPSEO_Meta'))) {
				return 'yoast';
			}
			if ($id === 'rankmath' && (defined('RANK_MATH_VERSION') || class_exists('\RankMath\Helper'))) {
				return 'rankmath';
			}
			if ($id === 'seopress' && (defined('SEOPRESS_VERSION') || function_exists('seopress_get_service'))) {
				return 'seopress';
			}
		}

		return 'none';
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	private function extract_yoast(int $post_id, array $payload): array {
		$k = function (string $key) use ($post_id) {
			return get_post_meta($post_id, $key, true);
		};

		$payload['title']       = $this->s((string) $k('_yoast_wpseo_title'));
		$payload['description'] = $this->s((string) $k('_yoast_wpseo_metadesc'));
		$payload['focus_keyword']= $this->s((string) $k('_yoast_wpseo_focuskw'));
		$payload['canonical']   = esc_url_raw((string) $k('_yoast_wpseo_canonical'));

		// Robots: Yoast stores "meta-robots-noindex" and "meta-robots-nofollow" (1 means yes)
		$noindex  = (string) $k('_yoast_wpseo_meta-robots-noindex');
		$nofollow = (string) $k('_yoast_wpseo_meta-robots-nofollow');

		$payload['robots'] = [
			'index'  => ($noindex === '1') ? false : null,
			'follow' => ($nofollow === '1') ? false : null,
		];

		$payload['og'] = [
			'title'       => $this->s((string) $k('_yoast_wpseo_opengraph-title')),
			'description' => $this->s((string) $k('_yoast_wpseo_opengraph-description')),
			'image_id'    => (int) $k('_yoast_wpseo_opengraph-image-id'),
			'image_url'   => esc_url_raw((string) $k('_yoast_wpseo_opengraph-image')),
		];

		$payload['twitter'] = [
			'title'       => $this->s((string) $k('_yoast_wpseo_twitter-title')),
			'description' => $this->s((string) $k('_yoast_wpseo_twitter-description')),
			'image_id'    => (int) $k('_yoast_wpseo_twitter-image-id'),
			'image_url'   => esc_url_raw((string) $k('_yoast_wpseo_twitter-image')),
		];

		$payload['raw'] = [
			'_yoast_wpseo_title'    => $payload['title'],
			'_yoast_wpseo_metadesc' => $payload['description'],
		];

		return $payload;
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	private function extract_rankmath(int $post_id, array $payload): array {
		$k = function (string $key) use ($post_id) {
			return get_post_meta($post_id, $key, true);
		};

		$payload['title']       = $this->s((string) $k('rank_math_title'));
		$payload['description'] = $this->s((string) $k('rank_math_description'));
		$payload['focus_keyword']= $this->s((string) $k('rank_math_focus_keyword'));
		$payload['canonical']   = esc_url_raw((string) $k('rank_math_canonical_url'));

		$robots = $k('rank_math_robots');
		$index = null;
		$follow = null;
		if (is_array($robots)) {
			// Typical: ['index','follow'] or includes 'noindex','nofollow'
			if (in_array('noindex', $robots, true)) {
				$index = false;
			}
			if (in_array('nofollow', $robots, true)) {
				$follow = false;
			}
		} elseif (is_string($robots) && $robots !== '') {
			$maybe = json_decode($robots, true);
			if (is_array($maybe)) {
				if (in_array('noindex', $maybe, true)) {
					$index = false;
				}
				if (in_array('nofollow', $maybe, true)) {
					$follow = false;
				}
			}
		}

		$payload['robots'] = ['index' => $index, 'follow' => $follow];

		$payload['og'] = [
			'title'       => $this->s((string) $k('rank_math_facebook_title')),
			'description' => $this->s((string) $k('rank_math_facebook_description')),
			'image_id'    => (int) $k('rank_math_facebook_image_id'),
			'image_url'   => esc_url_raw((string) $k('rank_math_facebook_image')),
		];

		$payload['twitter'] = [
			'title'       => $this->s((string) $k('rank_math_twitter_title')),
			'description' => $this->s((string) $k('rank_math_twitter_description')),
			'image_id'    => (int) $k('rank_math_twitter_image_id'),
			'image_url'   => esc_url_raw((string) $k('rank_math_twitter_image')),
		];

		$payload['raw'] = [
			'rank_math_title'       => $payload['title'],
			'rank_math_description' => $payload['description'],
		];

		return $payload;
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	private function extract_seopress(int $post_id, array $payload): array {
		$k = function (string $key) use ($post_id) {
			return get_post_meta($post_id, $key, true);
		};

		$payload['title']       = $this->s((string) $k('_seopress_titles_title'));
		$payload['description'] = $this->s((string) $k('_seopress_titles_desc'));
		$payload['focus_keyword']= $this->s((string) $k('_seopress_analysis_target_kw'));
		$payload['canonical']   = esc_url_raw((string) $k('_seopress_advanced_canonical'));

		// Robots in SEOPress varies; keep best-effort only (do not force).
		$payload['robots'] = ['index' => null, 'follow' => null];

		$payload['og'] = [
			'title'       => $this->s((string) $k('_seopress_social_fb_title')),
			'description' => $this->s((string) $k('_seopress_social_fb_desc')),
			'image_id'    => 0,
			'image_url'   => esc_url_raw((string) $k('_seopress_social_fb_img')),
		];

		$payload['twitter'] = [
			'title'       => $this->s((string) $k('_seopress_social_twitter_title')),
			'description' => $this->s((string) $k('_seopress_social_twitter_desc')),
			'image_id'    => 0,
			'image_url'   => esc_url_raw((string) $k('_seopress_social_twitter_img')),
		];

		$payload['raw'] = [
			'_seopress_titles_title' => $payload['title'],
			'_seopress_titles_desc'  => $payload['description'],
		];

		return $payload;
	}

	private function s(string $v): string {
		$v = wp_strip_all_tags($v);
		$v = trim($v);
		return $v;
	}
}
