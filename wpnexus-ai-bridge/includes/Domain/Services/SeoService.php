<?php
namespace WPNexusAIBridge\Domain\Services;

use WPNexusAIBridge\Logging\Logger;

if (!defined('ABSPATH')) {
	exit;
}

final class SeoService {

	/** @var Logger */
	private $logger;

	public function __construct() {
		$this->logger = Logger::instance();
	}

	/**
	 * @param array<string,mixed> $seo
	 * @param array<string,mixed> $payload full posts/upsert payload (for context)
	 */
	public function apply(int $post_id, array $seo, array $payload = []): void {
		$post_id = (int) $post_id;
		if ($post_id <= 0 || empty($seo)) {
			return;
		}

		$adapter = $this->detect_adapter();
		$seo     = $this->normalize($seo);

		// Canonical routing
		$canonical = $this->resolve_canonical($post_id, $seo);
		if ($canonical !== '') {
			$seo['canonical'] = $canonical;
		}

		// Default OG image from featured image if not provided
		if (empty($seo['og']['image_id']) && empty($seo['og']['image_url'])) {
			$thumb_id = (int) get_post_thumbnail_id($post_id);
			if ($thumb_id > 0) {
				$seo['og']['image_id']  = $thumb_id;
				$seo['og']['image_url'] = (string) wp_get_attachment_url($thumb_id);
			}
		}

		$this->logger->info('seo.apply.start', [
			'post_id' => $post_id,
			'adapter' => $adapter,
		]);

		if ($adapter === 'yoast') {
			$this->apply_yoast($post_id, $seo);
		} elseif ($adapter === 'rankmath') {
			$this->apply_rankmath($post_id, $seo);
		} elseif ($adapter === 'seopress') {
			$this->apply_seopress($post_id, $seo);
		} else {
			$this->apply_fallback($post_id, $seo);
		}

		$this->logger->info('seo.apply.done', [
			'post_id' => $post_id,
			'adapter' => $adapter,
		]);
	}

	private function detect_adapter(): string {
		if (defined('WPSEO_VERSION') || class_exists('WPSEO_Meta')) {
			return 'yoast';
		}
		if (defined('RANK_MATH_VERSION') || class_exists('\RankMath\Helper')) {
			return 'rankmath';
		}
		if (defined('SEOPRESS_VERSION') || function_exists('seopress_get_service')) {
			return 'seopress';
		}
		return 'none';
	}

	/**
	 * @param array<string,mixed> $seo
	 * @return array<string,mixed>
	 */
	private function normalize(array $seo): array {
		$out = $seo;

		$out['title']       = isset($seo['title']) ? $this->s((string) $seo['title']) : '';
		$out['description'] = isset($seo['description']) ? $this->s((string) $seo['description']) : '';
		$out['focus_keyword']= isset($seo['focus_keyword']) ? $this->s((string) $seo['focus_keyword']) : '';
		$out['canonical']   = isset($seo['canonical']) ? esc_url_raw((string) $seo['canonical']) : '';

		$out['robots'] = is_array($seo['robots'] ?? null) ? $seo['robots'] : ['index' => null, 'follow' => null];

		$out['og'] = is_array($seo['og'] ?? null) ? $seo['og'] : [];
		$out['og'] = array_merge(['title'=>'','description'=>'','image_id'=>0,'image_url'=>''], $out['og']);
		$out['og']['title'] = $this->s((string) ($out['og']['title'] ?? ''));
		$out['og']['description'] = $this->s((string) ($out['og']['description'] ?? ''));
		$out['og']['image_id'] = (int) ($out['og']['image_id'] ?? 0);
		$out['og']['image_url'] = esc_url_raw((string) ($out['og']['image_url'] ?? ''));

		$out['twitter'] = is_array($seo['twitter'] ?? null) ? $seo['twitter'] : [];
		$out['twitter'] = array_merge(['title'=>'','description'=>'','image_id'=>0,'image_url'=>''], $out['twitter']);
		$out['twitter']['title'] = $this->s((string) ($out['twitter']['title'] ?? ''));
		$out['twitter']['description'] = $this->s((string) ($out['twitter']['description'] ?? ''));
		$out['twitter']['image_id'] = (int) ($out['twitter']['image_id'] ?? 0);
		$out['twitter']['image_url'] = esc_url_raw((string) ($out['twitter']['image_url'] ?? ''));

		$out['canonical_mode']   = isset($seo['canonical_mode']) ? sanitize_key((string) $seo['canonical_mode']) : '';
		$out['canonical_custom'] = isset($seo['canonical_custom']) ? esc_url_raw((string) $seo['canonical_custom']) : '';
		$out['source_url']       = isset($seo['source_url']) ? esc_url_raw((string) $seo['source_url']) : '';

		return $out;
	}

	private function resolve_canonical(int $post_id, array $seo): string {
		$mode = isset($seo['canonical_mode']) ? sanitize_key((string) $seo['canonical_mode']) : '';
		if ($mode === 'custom') {
			return isset($seo['canonical_custom']) ? esc_url_raw((string) $seo['canonical_custom']) : '';
		}
		if ($mode === 'source') {
			return isset($seo['source_url']) ? esc_url_raw((string) $seo['source_url']) : '';
		}
		if ($mode === 'self') {
			return esc_url_raw((string) get_permalink($post_id));
		}
		// If mode not present, keep provided canonical (if any).
		return isset($seo['canonical']) ? esc_url_raw((string) $seo['canonical']) : '';
	}

	private function apply_yoast(int $post_id, array $seo): void {
		// Basic
		if ($seo['title'] !== '') {
			update_post_meta($post_id, '_yoast_wpseo_title', $seo['title']);
		}
		if ($seo['description'] !== '') {
			update_post_meta($post_id, '_yoast_wpseo_metadesc', $seo['description']);
		}
		if ($seo['focus_keyword'] !== '') {
			update_post_meta($post_id, '_yoast_wpseo_focuskw', $seo['focus_keyword']);
		}
		if (!empty($seo['canonical'])) {
			update_post_meta($post_id, '_yoast_wpseo_canonical', $seo['canonical']);
		}

		// Robots (only if explicitly set)
		$index  = $seo['robots']['index'] ?? null;
		$follow = $seo['robots']['follow'] ?? null;

		if ($index === false) {
			update_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', '1');
		}
		if ($follow === false) {
			update_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', '1');
		}

		// OG
		if ($seo['og']['title'] !== '') {
			update_post_meta($post_id, '_yoast_wpseo_opengraph-title', $seo['og']['title']);
		}
		if ($seo['og']['description'] !== '') {
			update_post_meta($post_id, '_yoast_wpseo_opengraph-description', $seo['og']['description']);
		}
		if (!empty($seo['og']['image_id'])) {
			update_post_meta($post_id, '_yoast_wpseo_opengraph-image-id', (int) $seo['og']['image_id']);
		}
		if (!empty($seo['og']['image_url'])) {
			update_post_meta($post_id, '_yoast_wpseo_opengraph-image', $seo['og']['image_url']);
		}

		// Twitter
		if ($seo['twitter']['title'] !== '') {
			update_post_meta($post_id, '_yoast_wpseo_twitter-title', $seo['twitter']['title']);
		}
		if ($seo['twitter']['description'] !== '') {
			update_post_meta($post_id, '_yoast_wpseo_twitter-description', $seo['twitter']['description']);
		}
		if (!empty($seo['twitter']['image_id'])) {
			update_post_meta($post_id, '_yoast_wpseo_twitter-image-id', (int) $seo['twitter']['image_id']);
		}
		if (!empty($seo['twitter']['image_url'])) {
			update_post_meta($post_id, '_yoast_wpseo_twitter-image', $seo['twitter']['image_url']);
		}
	}

	private function apply_rankmath(int $post_id, array $seo): void {
		if ($seo['title'] !== '') {
			update_post_meta($post_id, 'rank_math_title', $seo['title']);
		}
		if ($seo['description'] !== '') {
			update_post_meta($post_id, 'rank_math_description', $seo['description']);
		}
		if ($seo['focus_keyword'] !== '') {
			update_post_meta($post_id, 'rank_math_focus_keyword', $seo['focus_keyword']);
		}
		if (!empty($seo['canonical'])) {
			update_post_meta($post_id, 'rank_math_canonical_url', $seo['canonical']);
		}

		// OG(Facebook)
		if ($seo['og']['title'] !== '') {
			update_post_meta($post_id, 'rank_math_facebook_title', $seo['og']['title']);
		}
		if ($seo['og']['description'] !== '') {
			update_post_meta($post_id, 'rank_math_facebook_description', $seo['og']['description']);
		}
		if (!empty($seo['og']['image_id'])) {
			update_post_meta($post_id, 'rank_math_facebook_image_id', (int) $seo['og']['image_id']);
		}
		if (!empty($seo['og']['image_url'])) {
			update_post_meta($post_id, 'rank_math_facebook_image', $seo['og']['image_url']);
		}

		// Twitter
		if ($seo['twitter']['title'] !== '') {
			update_post_meta($post_id, 'rank_math_twitter_title', $seo['twitter']['title']);
		}
		if ($seo['twitter']['description'] !== '') {
			update_post_meta($post_id, 'rank_math_twitter_description', $seo['twitter']['description']);
		}
		if (!empty($seo['twitter']['image_id'])) {
			update_post_meta($post_id, 'rank_math_twitter_image_id', (int) $seo['twitter']['image_id']);
		}
		if (!empty($seo['twitter']['image_url'])) {
			update_post_meta($post_id, 'rank_math_twitter_image', $seo['twitter']['image_url']);
		}
	}

	private function apply_seopress(int $post_id, array $seo): void {
		if ($seo['title'] !== '') {
			update_post_meta($post_id, '_seopress_titles_title', $seo['title']);
		}
		if ($seo['description'] !== '') {
			update_post_meta($post_id, '_seopress_titles_desc', $seo['description']);
		}
		if ($seo['focus_keyword'] !== '') {
			update_post_meta($post_id, '_seopress_analysis_target_kw', $seo['focus_keyword']);
		}
		if (!empty($seo['canonical'])) {
			update_post_meta($post_id, '_seopress_advanced_canonical', $seo['canonical']);
		}

		// Social (best-effort)
		if ($seo['og']['title'] !== '') {
			update_post_meta($post_id, '_seopress_social_fb_title', $seo['og']['title']);
		}
		if ($seo['og']['description'] !== '') {
			update_post_meta($post_id, '_seopress_social_fb_desc', $seo['og']['description']);
		}
		if (!empty($seo['og']['image_url'])) {
			update_post_meta($post_id, '_seopress_social_fb_img', $seo['og']['image_url']);
		}

		if ($seo['twitter']['title'] !== '') {
			update_post_meta($post_id, '_seopress_social_twitter_title', $seo['twitter']['title']);
		}
		if ($seo['twitter']['description'] !== '') {
			update_post_meta($post_id, '_seopress_social_twitter_desc', $seo['twitter']['description']);
		}
		if (!empty($seo['twitter']['image_url'])) {
			update_post_meta($post_id, '_seopress_social_twitter_img', $seo['twitter']['image_url']);
		}
	}

	private function apply_fallback(int $post_id, array $seo): void {
		if ($seo['title'] !== '') {
			update_post_meta($post_id, '_wpnexus_seo_title', $seo['title']);
		}
		if ($seo['description'] !== '') {
			update_post_meta($post_id, '_wpnexus_seo_description', $seo['description']);
		}
		if (!empty($seo['canonical'])) {
			update_post_meta($post_id, '_wpnexus_seo_canonical', $seo['canonical']);
		}
	}

	private function s(string $v): string {
		$v = wp_strip_all_tags($v);
		return trim($v);
	}
}
