<?php
namespace WPNexusAIBridge\Domain\Services;

use WP_Error;
use WPNexusAIBridge\Logging\Logger;

if (!defined('ABSPATH')) {
	exit;
}

final class LanguageAssignmentService {

	private $logger;

	public function __construct() {
		$this->logger = Logger::instance();
	}

	public function assign_post_language(int $post_id, string $language_code) {
		$language_code = sanitize_key($language_code);
		if ($language_code === '') {
			return new WP_Error('wpnexus_bridge_language_invalid', t('rest_language_invalid'), ['status' => 400]);
		}

		// Polylang
		if (function_exists('pll_set_post_language')) {
			$available = function_exists('pll_languages_list') ? pll_languages_list() : [];
			if (is_array($available) && !empty($available) && !in_array($language_code, $available, true)) {
				return new WP_Error('wpnexus_bridge_language_invalid', t('rest_language_invalid'), ['status' => 400]);
			}

			pll_set_post_language($post_id, $language_code);

			$this->logger->info('bridge.lang.polylang.post.assigned', [
				'post_id' => $post_id,
				'lang'    => $language_code,
			]);

			return true;
		}

		// WPML (best-effort)
		if (defined('ICL_SITEPRESS_VERSION') || class_exists('SitePress')) {
			$post_type = get_post_type($post_id);
			$element_type = 'post_' . $post_type;

			$details = apply_filters('wpml_element_language_details', null, [
				'element_id'   => $post_id,
				'element_type' => $element_type,
			]);

			$trid = (is_object($details) && isset($details->trid)) ? $details->trid : false;
			$src  = (is_object($details) && isset($details->source_language_code)) ? $details->source_language_code : null;

			do_action('wpml_set_element_language_details', [
				'element_id'           => $post_id,
				'element_type'         => $element_type,
				'trid'                 => $trid,
				'language_code'        => $language_code,
				'source_language_code' => $src,
			]);

			$this->logger->info('bridge.lang.wpml.post.assigned', [
				'post_id' => $post_id,
				'lang'    => $language_code,
			]);

			return true;
		}

		// No multilingual plugin — no-op
		$this->logger->info('bridge.lang.none.noop', [
			'post_id' => $post_id,
			'lang'    => $language_code,
		]);

		return true;
	}

	public function assign_term_language(int $term_id, string $taxonomy, string $language_code) {
		$language_code = sanitize_key($language_code);
		$taxonomy = sanitize_key($taxonomy);

		if ($language_code === '' || $taxonomy === '') {
			return true;
		}

		// Polylang
		if (function_exists('pll_set_term_language')) {
			pll_set_term_language($term_id, $language_code);
			$this->logger->info('bridge.lang.polylang.term.assigned', [
				'term_id'  => $term_id,
				'taxonomy' => $taxonomy,
				'lang'     => $language_code,
			]);
			return true;
		}

		// WPML
		if (defined('ICL_SITEPRESS_VERSION') || class_exists('SitePress')) {
			$element_type = 'tax_' . $taxonomy;

			$details = apply_filters('wpml_element_language_details', null, [
				'element_id'   => $term_id,
				'element_type' => $element_type,
			]);

			$trid = (is_object($details) && isset($details->trid)) ? $details->trid : false;
			$src  = (is_object($details) && isset($details->source_language_code)) ? $details->source_language_code : null;

			do_action('wpml_set_element_language_details', [
				'element_id'           => $term_id,
				'element_type'         => $element_type,
				'trid'                 => $trid,
				'language_code'        => $language_code,
				'source_language_code' => $src,
			]);

			$this->logger->info('bridge.lang.wpml.term.assigned', [
				'term_id'  => $term_id,
				'taxonomy' => $taxonomy,
				'lang'     => $language_code,
			]);
		}

		return true;
	}
}
