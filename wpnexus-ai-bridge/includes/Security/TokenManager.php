<?php
namespace WPNexusAIBridge\Security;

use WPNexusAIBridge\Logging\Logger;

if (!defined('ABSPATH')) {
	exit;
}

final class TokenManager {

	private const OPT_HASH           = 'wpnexus_ai_bridge_token_hash';
	private const OPT_LAST_PLAIN     = 'wpnexus_ai_bridge_token_last_plain';
	private const OPT_LAST_ISSUED_AT = 'wpnexus_ai_bridge_token_last_issued_at';
	private const OPT_TOKEN_USER_ID  = 'wpnexus_ai_bridge_token_user_id';

	public static function ensure_exists(): void {
		$hash = get_option(self::OPT_HASH, '');
		if (is_string($hash) && $hash !== '') {
			self::ensure_token_user();
			return;
		}

		self::regenerate();
		self::ensure_token_user();
	}

	/**
	 * Generates a new token.
	 * Stores ONLY hash persistently,
	 * stores plain token temporarily (admin view).
	 *
	 * @return string Plain token (show once)
	 */
	public static function regenerate(): string {
		$logger = Logger::instance();

		$plain = self::generate_plain_token();
		$hash  = hash('sha256', $plain);

		update_option(self::OPT_HASH, $hash, false);
		update_option(self::OPT_LAST_PLAIN, $plain, false);
		update_option(self::OPT_LAST_ISSUED_AT, time(), false);

		$logger->info('bridge.token.regenerated', [
			'fingerprint' => substr($hash, 0, 12),
		]);

		return $plain;
	}

	public static function hide_last_plain(): void {
		delete_option(self::OPT_LAST_PLAIN);
		Logger::instance()->info('bridge.token.plain.hidden');
	}

	public static function get_last_plain(): ?string {
		$plain = get_option(self::OPT_LAST_PLAIN, '');
		if (!is_string($plain) || $plain === '') {
			return null;
		}
		return $plain;
	}

	public static function get_hash(): ?string {
		$hash = get_option(self::OPT_HASH, '');
		if (!is_string($hash) || $hash === '') {
			return null;
		}
		return $hash;
	}

	public static function token_user_id(): int {
		return (int) get_option(self::OPT_TOKEN_USER_ID, 0);
	}

	public static function ensure_token_user(): void {
		$logger = Logger::instance();

		$uid = (int) get_option(self::OPT_TOKEN_USER_ID, 0);
		if ($uid > 0) {
			return;
		}

		$uid = (int) get_current_user_id();

		// fallback: first admin
		if ($uid <= 0) {
			$admins = get_users([
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			]);
			if (!empty($admins) && isset($admins[0])) {
				$uid = (int) $admins[0];
			}
		}

		if ($uid > 0) {
			update_option(self::OPT_TOKEN_USER_ID, $uid, false);
			$logger->info('bridge.token.user.set', ['user_id' => $uid]);
		} else {
			$logger->warning('bridge.token.user.missing');
		}
	}

	private static function generate_plain_token(): string {
		try {
			$bytes = random_bytes(24);
		} catch (\Throwable $e) {
			$bytes = (string) wp_generate_password(48, false, false);
			return 'wpnx_' . substr(hash('sha256', $bytes), 0, 48);
		}

		$b64 = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
		return 'wpnx_' . $b64;
	}
}
