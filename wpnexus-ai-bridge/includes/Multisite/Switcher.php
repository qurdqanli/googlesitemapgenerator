<?php
namespace WPNexusAIBridge\Multisite;

use WP_Error;
use WP_REST_Request;
use WPNexusAIBridge\Logging\Logger;

if (!defined('ABSPATH')) {
	exit;
}

final class Switcher {

	private const NS_PREFIX = '/wpnexus-bridge/v1/';
	private static $switched = false;

	public static function register(): void {
		add_filter('rest_pre_dispatch', [__CLASS__, 'maybe_switch'], 0, 3);
		add_filter('rest_post_dispatch', [__CLASS__, 'maybe_restore'], 999, 3);

		Logger::instance()->info('multisite.switcher.registered');
	}

	public static function maybe_switch($result, $server, WP_REST_Request $request) {
		$route = (string) $request->get_route();

		if (strpos($route, self::NS_PREFIX) !== 0) {
			return $result;
		}

		if (!is_multisite()) {
			return $result;
		}

		$site_id = (int) $request->get_header('X-WPNexus-Network-Site');
		if ($site_id <= 0) {
			return $result;
		}

		$current = (int) get_current_blog_id();
		if ($site_id === $current) {
			return $result;
		}

		wp_get_current_user();
		if (!is_super_admin()) {
			Logger::instance()->warning('multisite.switch.forbidden', [
				'route'     => $route,
				'requested' => $site_id,
				'current'   => $current,
				'uid'       => get_current_user_id(),
			]);
			return new WP_Error('wpnexus_bridge_multisite_forbidden', t('rest_multisite_forbidden'), [
				'status' => 403,
			]);
		}

		$details = get_blog_details($site_id, false);
		if (!$details) {
			Logger::instance()->warning('multisite.switch.not_found', [
				'route'   => $route,
				'site_id' => $site_id,
			]);
			return new WP_Error('wpnexus_bridge_site_not_found', t('rest_site_not_found'), [
				'status' => 404,
			]);
		}

		if (switch_to_blog($site_id)) {
			self::$switched = true;
			Logger::instance()->info('multisite.switch.ok', [
				'from' => $current,
				'to'   => $site_id,
			]);
		}

		return $result;
	}

	public static function maybe_restore($response, $server, WP_REST_Request $request) {
		$route = (string) $request->get_route();

		if (strpos($route, self::NS_PREFIX) !== 0) {
			return $response;
		}

		if (self::$switched) {
			restore_current_blog();
			self::$switched = false;
			Logger::instance()->debug('multisite.restore.ok', [
				'blog_id' => get_current_blog_id(),
			]);
		}

		return $response;
	}
}
