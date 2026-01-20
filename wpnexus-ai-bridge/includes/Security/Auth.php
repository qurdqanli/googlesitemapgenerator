<?php
namespace WPNexusAIBridge\Security;

use WP_Error;
use WP_REST_Request;
use WPNexusAIBridge\Logging\Logger;

if (!defined('ABSPATH')) {
	exit;
}

final class Auth {

	private const NS = 'wpnexus-bridge/v1';

	public static function register(): void {
		$logger = Logger::instance();

		add_filter('determine_current_user', function ($user_id) use ($logger) {
			// Already authenticated (e.g., App Password Basic auth)
			if (!empty($user_id)) {
				return $user_id;
			}

			if (!self::is_bridge_rest_request()) {
				return $user_id;
			}

			$auth = self::authorization_header();
			if ($auth === '' || stripos($auth, 'Bearer ') !== 0) {
				return $user_id;
			}

			$token = trim(substr($auth, 7));
			if ($token === '') {
				return $user_id;
			}

			$hash = TokenManager::get_hash();
			if (!$hash) {
				$logger->warning('auth.bearer.no_hash');
				return $user_id;
			}

			$calc = hash('sha256', $token);
			if (!hash_equals($hash, $calc)) {
				$logger->warning('auth.bearer.invalid', [
					'fingerprint' => substr($calc, 0, 12),
				]);
				return $user_id;
			}

			$uid = TokenManager::token_user_id();
			if ($uid <= 0) {
				$logger->warning('auth.bearer.user_missing');
				return $user_id;
			}

			$logger->info('auth.bearer.ok', [
				'user_id'     => $uid,
				'fingerprint' => substr($calc, 0, 12),
			]);

			return $uid;
		}, 30);

		$logger->info('auth.registered');
	}

	public static function permission(string $capability = 'manage_options'): callable {
		return function (WP_REST_Request $request) use ($capability) {
			$logger = Logger::instance();

			wp_get_current_user();

			if (!is_user_logged_in()) {
				$logger->warning('auth.required', [
					'route' => $request->get_route(),
				]);
				return new WP_Error('wpnexus_bridge_auth_required', t('rest_auth_required'), [
					'status' => 401,
				]);
			}

			if (!current_user_can($capability)) {
				$logger->warning('auth.forbidden', [
					'route' => $request->get_route(),
					'cap'   => $capability,
					'uid'   => get_current_user_id(),
				]);
				return new WP_Error('wpnexus_bridge_forbidden', t('rest_forbidden'), [
					'status' => 403,
				]);
			}

			return true;
		};
	}

	private static function is_bridge_rest_request(): bool {
		if (!defined('REST_REQUEST') || !REST_REQUEST) {
			return false;
		}

		$uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ($uri === '') {
			return false;
		}

		$prefix = function_exists('rest_get_url_prefix') ? rest_get_url_prefix() : 'wp-json';
		$needle = '/' . trim($prefix, '/') . '/' . self::NS . '/';

		return (strpos($uri, $needle) !== false);
	}

	private static function authorization_header(): string {
		$h = '';
		if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
			$h = (string) $_SERVER['HTTP_AUTHORIZATION'];
		} elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
			$h = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
		}

		if ($h === '' && function_exists('getallheaders')) {
			$headers = getallheaders();
			if (is_array($headers)) {
				foreach ($headers as $k => $v) {
					if (strtolower((string) $k) === 'authorization') {
						$h = (string) $v;
						break;
					}
				}
			}
		}

		return trim($h);
	}
}
