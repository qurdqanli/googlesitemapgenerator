<?php
namespace WPNexusAIBridge\Domain\Services;

use WP_Error;
use WPNexusAIBridge\Logging\Logger;

if (!defined('ABSPATH')) {
	exit;
}

final class TermsService {

	/** @var Logger */
	private $logger;

	/** @var LanguageAssignmentService */
	private $lang;

	public function __construct() {
		$this->logger = Logger::instance();
		$this->lang   = new LanguageAssignmentService();
	}

	/**
	 * Search terms in taxonomy.
	 *
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public function search(string $taxonomy, string $search = '') {
		$taxonomy = sanitize_key($taxonomy);
		$search   = sanitize_text_field($search);

		if ($taxonomy === '' || !taxonomy_exists($taxonomy)) {
			return new WP_Error('wpnexus_bridge_taxonomy_invalid', t('rest_taxonomy_invalid'), ['status' => 400]);
		}

		$args = [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => 50,
		];

		if ($search !== '') {
			$args['search'] = $search;
		}

		$terms = get_terms($args);
		if (is_wp_error($terms)) {
			return $terms;
		}

		$out = [];
		foreach ($terms as $t) {
			$out[] = [
				'term_id'  => (int) $t->term_id,
				'slug'     => (string) $t->slug,
				'name'     => (string) $t->name,
				'parent'   => (int) $t->parent,
				'taxonomy' => (string) $taxonomy,
			];
		}

		return $out;
	}

	/**
	 * Create or update a term by slug.
	 *
	 * Expected payload keys:
	 * - taxonomy (required)
	 * - slug (optional, will be sanitized from name if missing)
	 * - name (required)
	 * - parent (optional term_id)
	 * - description (optional)
	 * - language_code (optional)
	 *
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>|WP_Error
	 */
	public function upsert(array $payload) {
		$taxonomy = isset($payload['taxonomy']) ? sanitize_key((string) $payload['taxonomy']) : '';
		$name     = isset($payload['name']) ? sanitize_text_field((string) $payload['name']) : '';
		$slug_in  = isset($payload['slug']) ? sanitize_title((string) $payload['slug']) : '';
		$parent   = isset($payload['parent']) ? (int) $payload['parent'] : 0;
		$desc     = isset($payload['description']) ? sanitize_textarea_field((string) $payload['description']) : '';
		$lang     = isset($payload['language_code']) ? sanitize_key((string) $payload['language_code']) : '';

		if ($taxonomy === '' || !taxonomy_exists($taxonomy)) {
			return new WP_Error('wpnexus_bridge_taxonomy_invalid', t('rest_taxonomy_invalid'), ['status' => 400]);
		}

		if ($name === '') {
			return new WP_Error('wpnexus_bridge_term_name_required', t('rest_terms_name_required'), ['status' => 400]);
		}

		$slug = $slug_in !== '' ? $slug_in : sanitize_title($name);

		$existing = get_term_by('slug', $slug, $taxonomy);
		$action = 'created';

		if ($existing && !is_wp_error($existing) && isset($existing->term_id)) {
			$term_id = (int) $existing->term_id;
			$res = wp_update_term($term_id, $taxonomy, [
				'name'        => $name,
				'slug'        => $slug,
				'parent'      => $parent > 0 ? $parent : 0,
				'description' => $desc,
			]);
			$action = 'updated';
		} else {
			$res = wp_insert_term($name, $taxonomy, [
				'slug'        => $slug,
				'parent'      => $parent > 0 ? $parent : 0,
				'description' => $desc,
			]);
		}

		if (is_wp_error($res)) {
			$this->logger->warning('bridge.terms.upsert.fail', [
				'taxonomy' => $taxonomy,
				'slug'     => $slug,
				'code'     => $res->get_error_code(),
				'msg'      => $res->get_error_message(),
			]);
			return new WP_Error('wpnexus_bridge_term_upsert_failed', t('rest_terms_upsert_failed'), ['status' => 500]);
		}

		$term_id = isset($res['term_id']) ? (int) $res['term_id'] : 0;

		if ($term_id > 0 && $lang !== '') {
			$this->lang->assign_term_language($term_id, $taxonomy, $lang);
		}

		return [
			'term_id'  => $term_id,
			'taxonomy' => $taxonomy,
			'slug'     => $slug,
			'name'     => $name,
			'action'   => $action,
		];
	}
}

