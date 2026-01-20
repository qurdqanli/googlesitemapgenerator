<?php
namespace WPNexusAI\Engine\Mapping;

use WP_Error;
use WPNexusAI\Logging\Logger;
use WPNexusAI\DB\Repos\MappingTermsRepo;
use WPNexusAI\Bridge\Client\BridgeClient;

if (!defined('ABSPATH')) {
	exit;
}

final class TermsMapper {

	/** @var Logger */
	private $logger;

	/** @var MappingTermsRepo */
	private $repo;

	/** @var BridgeClient */
	private $bridge;

	/** @var array<string,int> */
	private $cache = [];

	/** @var array<string,bool> */
	private $resolving = [];

	public function __construct() {
		$this->logger = Logger::instance();
		$this->repo   = new MappingTermsRepo();
		$this->bridge = new BridgeClient();
	}

	/**
	 * Build terms payload for Bridge posts/upsert.
	 *
	 * Returns: array taxonomy => list of items where each item includes term_id (target).
	 *
	 * @param array<int,array<string,mixed>> $source_terms extracted terms list (taxonomy,term_id,slug,name,parent)
	 * @param array<string,mixed> $target_row
	 * @param array<string,mixed> $job_payload used for overrides (optional)
	 * @return array<string,array<int,array<string,mixed>>>|WP_Error
	 */
	public function map_terms(array $source_terms, array $target_row, string $language_code, array $job_payload) {
		$language_code = sanitize_key($language_code);
		$target_id = isset($target_row['id']) ? (int) $target_row['id'] : 0;

		if ($target_id <= 0 || $language_code === '') {
			return new WP_Error('wpnexus_terms_map_invalid', t('mapping_err_invalid'));
		}

		// Index source terms by taxonomy+source_term_id for fast parent lookup
		$index = [];
		foreach ($source_terms as $t) {
			if (!is_array($t)) {
				continue;
			}
			$tax = isset($t['taxonomy']) ? sanitize_key((string) $t['taxonomy']) : '';
			$sid = isset($t['term_id']) ? (int) $t['term_id'] : 0;
			if ($tax === '' || $sid <= 0) {
				continue;
			}
			$index[$tax . ':' . $sid] = $t;
		}

		// Optional overrides in job payload:
		// terms_overrides[taxonomy][source_term_id] = target_term_id
		$overrides = [];
		if (!empty($job_payload['terms_overrides']) && is_array($job_payload['terms_overrides'])) {
			$overrides = $job_payload['terms_overrides'];
		}

		$out = [];

		foreach ($source_terms as $t) {
			if (!is_array($t)) {
				continue;
			}
			$tax = isset($t['taxonomy']) ? sanitize_key((string) $t['taxonomy']) : '';
			$sid = isset($t['term_id']) ? (int) $t['term_id'] : 0;

			if ($tax === '' || $sid <= 0) {
				continue;
			}

			$target_term_id = $this->resolve_term($target_row, $language_code, $t, $index, $overrides);
			if (is_wp_error($target_term_id)) {
				return $target_term_id;
			}

			if (!isset($out[$tax])) {
				$out[$tax] = [];
			}

			$out[$tax][] = [
				'term_id' => (int) $target_term_id,
				// include slug for debug/readability (Bridge ignores extra keys)
				'slug'    => isset($t['slug']) ? sanitize_title((string) $t['slug']) : '',
				'name'    => isset($t['name']) ? sanitize_text_field((string) $t['name']) : '',
			];
		}

		// Deduplicate per taxonomy by term_id
		foreach ($out as $tax => $items) {
			$seen = [];
			$dedup = [];
			foreach ($items as $it) {
				$id = isset($it['term_id']) ? (int) $it['term_id'] : 0;
				if ($id <= 0 || isset($seen[$id])) {
					continue;
				}
				$seen[$id] = true;
				$dedup[] = $it;
			}
			$out[$tax] = $dedup;
		}

		$this->logger->info('terms.map.done', [
			'target_id' => $target_id,
			'lang'      => $language_code,
			'tax_count' => count($out),
		]);

		return $out;
	}

	/**
	 * @param array<string,mixed> $target_row
	 * @param array<string,mixed> $term
	 * @param array<string,array<string,mixed>> $index
	 * @param array<string,mixed> $overrides
	 * @return int|WP_Error
	 */
	private function resolve_term(array $target_row, string $language_code, array $term, array $index, array $overrides) {
		$target_id = isset($target_row['id']) ? (int) $target_row['id'] : 0;

		$tax = isset($term['taxonomy']) ? sanitize_key((string) $term['taxonomy']) : '';
		$sid = isset($term['term_id']) ? (int) $term['term_id'] : 0;

		if ($target_id <= 0 || $tax === '' || $sid <= 0) {
			return new WP_Error('wpnexus_terms_map_invalid', t('mapping_err_invalid'));
		}

		$cache_key = $target_id . '|' . $tax . '|' . $sid . '|' . $language_code;
		if (isset($this->cache[$cache_key])) {
			return (int) $this->cache[$cache_key];
		}

		// Cycle protection for weird parent loops
		if (!empty($this->resolving[$cache_key])) {
			return new WP_Error('wpnexus_terms_map_cycle', t('mapping_err_cycle'));
		}
		$this->resolving[$cache_key] = true;

		// 1) Override
		if (isset($overrides[$tax]) && is_array($overrides[$tax]) && isset($overrides[$tax][$sid])) {
			$forced = (int) $overrides[$tax][$sid];
			if ($forced > 0) {
				$this->cache[$cache_key] = $forced;
				unset($this->resolving[$cache_key]);
				return $forced;
			}
		}

		// 2) Saved mapping
		$row = $this->repo->get($target_id, $tax, $sid, $language_code);
		if ($row && !empty($row['target_term_id'])) {
			$tid = (int) $row['target_term_id'];
			if ($tid > 0) {
				$this->cache[$cache_key] = $tid;
				unset($this->resolving[$cache_key]);
				return $tid;
			}
		}

		// 3/4) Auto-map/auto-create via Bridge /terms/upsert (idempotent: updates if exists, creates if missing)
		$slug  = isset($term['slug']) ? sanitize_title((string) $term['slug']) : '';
		$name  = isset($term['name']) ? sanitize_text_field((string) $term['name']) : '';
		$parent_source = isset($term['parent']) ? (int) $term['parent'] : 0;

		if ($name === '' && $slug === '') {
			unset($this->resolving[$cache_key]);
			return new WP_Error('wpnexus_terms_map_missing', t('mapping_err_term_missing'));
		}

		$parent_target = 0;
		if ($parent_source > 0) {
			$parent_key = $tax . ':' . $parent_source;
			if (isset($index[$parent_key]) && is_array($index[$parent_key])) {
				$parent_target_res = $this->resolve_term($target_row, $language_code, $index[$parent_key], $index, $overrides);
				if (is_wp_error($parent_target_res)) {
					unset($this->resolving[$cache_key]);
					return $parent_target_res;
				}
				$parent_target = (int) $parent_target_res;
			}
		}

		$payload = [
			'taxonomy'      => $tax,
			'slug'          => $slug !== '' ? $slug : sanitize_title($name),
			'name'          => $name !== '' ? $name : $slug,
			'parent'        => $parent_target,
			'language_code' => $language_code,
		];

		$this->logger->info('terms.map.upsert', [
			'target_id' => $target_id,
			'taxonomy'  => $tax,
			'source_id' => $sid,
			'slug'      => $payload['slug'],
			'parent_t'  => $parent_target,
		]);

		$res = $this->bridge->terms_upsert($target_row, $payload);

		if (!$res->ok) {
			$status = is_int($res->status) ? $res->status : 0;
			$err = $res->error ? (string) $res->error : t('mapping_err_bridge_http');

			$this->logger->warning('terms.map.upsert.fail', [
				'target_id' => $target_id,
				'taxonomy'  => $tax,
				'source_id' => $sid,
				'status'    => $status,
				'error'     => $err,
			]);

			// Bridge missing endpoint/plugin -> needs_input
			if ($status === 404) {
				unset($this->resolving[$cache_key]);
				return new WP_Error('wpnexus_terms_bridge_missing', t('mapping_err_bridge_missing'), ['status' => 404]);
			}

			// Retry-worthy (network/5xx) -> bubble up as error; UpsertTask can retry
			unset($this->resolving[$cache_key]);
			return new WP_Error('wpnexus_terms_bridge_fail', $err, ['status' => $status]);
		}

		$json = is_array($res->json) ? $res->json : [];
		$target_term_id = isset($json['term_id']) ? (int) $json['term_id'] : 0;

		if ($target_term_id <= 0) {
			unset($this->resolving[$cache_key]);
			return new WP_Error('wpnexus_terms_bad_response', t('mapping_err_bad_response'));
		}

		// Save mapping
		$this->repo->upsert($target_id, $tax, $sid, $language_code, $target_term_id, (string) $payload['slug']);

		$this->cache[$cache_key] = $target_term_id;
		unset($this->resolving[$cache_key]);

		return $target_term_id;
	}
}
