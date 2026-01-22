<?php
namespace WPNexusAI\Engine\Routing;

use WPNexusAI\Logging\Logger;
use WPNexusAI\Util\Lang;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Reads editor overrides (post meta) + job payload hints and returns routing settings.
 */
final class LanguageRouter {

	public const META_KEY = '_wpnexus_ai_overrides';

	/** @var Logger */
	private $logger;

	public function __construct() {
		$this->logger = Logger::instance();
	}

	/**
	 * @return array{language_pref:string,send_original:bool,source_lang:string}
	 */
	public function route(int $post_id, array $target_row, array $job_payload): array {
		$post_id  = (int) $post_id;
		$target_id = (int) ($target_row['id'] ?? 0);

		$meta = get_post_meta($post_id, self::META_KEY, true);
		$meta = is_array($meta) ? $meta : [];

		// Job payload can override meta (future bulk ops).
		$lang_pref = '';
		if (!empty($job_payload['language_code'])) {
			$lang_pref = (string) $job_payload['language_code'];
		} elseif (!empty($job_payload['target_lang'])) {
			$lang_pref = (string) $job_payload['target_lang'];
		}

		$send_original = isset($job_payload['send_original']) ? (bool) $job_payload['send_original'] : null;
		$source_lang   = !empty($job_payload['source_lang']) ? (string) $job_payload['source_lang'] : '';

		// Meta: global
		$meta_send_original = !empty($meta['send_original']);
		$meta_source_lang   = !empty($meta['source_lang']) ? (string) $meta['source_lang'] : '';

		// Meta: per target
		$per = [];
		if (!empty($meta['targets']) && is_array($meta['targets'])) {
			$per = $meta['targets'];
		}

		$per_lang = '';
		$per_send = null;

		if ($target_id > 0 && isset($per[$target_id]) && is_array($per[$target_id])) {
			$per_lang = !empty($per[$target_id]['language']) ? (string) $per[$target_id]['language'] : '';
			if (isset($per[$target_id]['send_original'])) {
				$per_send = (bool) $per[$target_id]['send_original'];
			}
		}

		if ($lang_pref === '') {
			$lang_pref = $per_lang !== '' ? $per_lang : 'auto';
		}
		$lang_pref = Lang::sanitize_code($lang_pref);
		if ($lang_pref === '') {
			$lang_pref = 'auto';
		}

		if ($send_original === null) {
			if ($per_send !== null) {
				$send_original = (bool) $per_send;
			} else {
				$send_original = (bool) $meta_send_original;
			}
		}

		if ($source_lang === '') {
			$source_lang = $meta_source_lang !== '' ? $meta_source_lang : Lang::from_locale((string) get_locale());
		}
		$source_lang = Lang::sanitize_code($source_lang);
		if ($source_lang === '') {
			$source_lang = 'auto';
		}

		$this->logger->debug('lang.route', [
			'post_id'       => $post_id,
			'target_id'     => $target_id,
			'language_pref' => $lang_pref,
			'send_original' => $send_original ? 1 : 0,
			'source_lang'   => $source_lang,
		]);

		/**
		 * Allow custom routing rules.
		 */
		$out = [
			'language_pref' => $lang_pref,
			'send_original' => (bool) $send_original,
			'source_lang'   => $source_lang,
		];

		return apply_filters('wpnexus_ai_language_route', $out, $post_id, $target_row, $job_payload);
	}
}
