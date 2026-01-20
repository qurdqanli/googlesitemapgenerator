<?php
namespace WPNexusAI\Security;

if (!defined('ABSPATH')) {
	exit;
}

final class Crypto {

	private const CIPHER = 'aes-256-gcm';

	/**
	 * Encrypt plain text into a base64 JSON payload.
	 */
	public static function encrypt(?string $plain): ?string {
		if ($plain === null || $plain === '') {
			return $plain;
		}

		if (!function_exists('openssl_encrypt')) {
			// Fallback: not ideal, but prevents fatal. Store as-is with marker.
			return 'plain:' . $plain;
		}

		$key = self::key();
		$iv  = random_bytes(12);
		$tag = '';

		$ciphertext = openssl_encrypt(
			$plain,
			self::CIPHER,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		if ($ciphertext === false) {
			return null;
		}

		$payload = [
			'v'   => 1,
			'iv'  => base64_encode($iv),
			'tag' => base64_encode($tag),
			'ct'  => base64_encode($ciphertext),
		];

		return base64_encode(wp_json_encode($payload));
	}

	/**
	 * Decrypt a base64 JSON payload back to plain text.
	 */
	public static function decrypt(?string $cipher): ?string {
		if ($cipher === null || $cipher === '') {
			return $cipher;
		}

		if (strncmp($cipher, 'plain:', 6) === 0) {
			return substr($cipher, 6);
		}

		if (!function_exists('openssl_decrypt')) {
			return null;
		}

		$json = base64_decode($cipher, true);
		if (!is_string($json) || $json === '') {
			return null;
		}

		$data = json_decode($json, true);
		if (!is_array($data) || empty($data['iv']) || empty($data['tag']) || empty($data['ct'])) {
			return null;
		}

		$iv  = base64_decode((string) $data['iv'], true);
		$tag = base64_decode((string) $data['tag'], true);
		$ct  = base64_decode((string) $data['ct'], true);

		if (!is_string($iv) || !is_string($tag) || !is_string($ct)) {
			return null;
		}

		$key = self::key();

		$plain = openssl_decrypt(
			$ct,
			self::CIPHER,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		return ($plain === false) ? null : $plain;
	}

	/**
	 * 32-byte key derived from WP salts.
	 */
	private static function key(): string {
		$material = wp_salt('auth') . '|' . wp_salt('secure_auth') . '|' . wp_salt('logged_in');
		return hash('sha256', $material, true);
	}
}
