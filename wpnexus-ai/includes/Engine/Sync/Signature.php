<?php
namespace WPNexusAI\Engine\Sync;

if (!defined('ABSPATH')) {
	exit;
}

final class Signature {

	public static function for_link(int $source_post_id, int $target_id, string $language_code): string {
		$source_post_id = (int) $source_post_id;
		$target_id      = (int) $target_id;
		$language_code  = sanitize_key($language_code);

		$raw = site_url() . '|' . $source_post_id . '|' . $target_id . '|' . $language_code;
		return 'wpnexus:' . sha1($raw);
	}
}
