<?php
namespace WPNexusAI\Engine\Sync;

if (!defined('ABSPATH')) {
	exit;
}

final class Signature {

	/**
	 * Canonical signature generator for linking source_post_id + target_id + language_code.
	 */
	public static function make(int $source_post_id, int $target_id, string $language_code): string {
		return self::for_link($source_post_id, $target_id, $language_code);
	}

	/**
	 * Back-compat alias (older code used for_link()).
	 */
	public static function for_link(int $source_post_id, int $target_id, string $language_code): string {
		$source_post_id = (int) $source_post_id;
		$target_id      = (int) $target_id;
		$language_code  = sanitize_key($language_code);

		$raw = site_url() . '|' . $source_post_id . '|' . $target_id . '|' . $language_code;
		return 'wpnexus:' . sha1($raw);
	}
}

