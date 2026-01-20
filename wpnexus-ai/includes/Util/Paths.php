<?php
namespace WPNexusAI\Util;

if (!defined('ABSPATH')) {
	exit;
}

final class Paths {

	public static function uploads_dir(): string {
		$u = wp_get_upload_dir();
		return rtrim($u['basedir'] ?? WP_CONTENT_DIR . '/uploads', '/\\');
	}

	public static function plugin_storage_dir(): string {
		return self::uploads_dir() . '/wpnexus-ai';
	}

	public static function logs_dir(): string {
		return self::plugin_storage_dir() . '/logs';
	}
}
