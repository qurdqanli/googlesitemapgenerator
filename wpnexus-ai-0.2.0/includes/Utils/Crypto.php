<?php
namespace WPNexusAI\Utils;

if (!defined('ABSPATH')) { exit; }

final class Crypto {

    private const OPT_KEY = 'wpnexus_ai_crypto_key_v1';

    /**
     * Encrypt plaintext into base64 string.
     */
    public static function encrypt(string $plaintext): string {
        if ($plaintext === '') { return ''; }
        $key = self::key();
        // Prefer libsodium if available.
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);
            return 'sbx:' . base64_encode($nonce . $cipher);
        }
        // Fallback to OpenSSL AES-256-GCM.
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) { return ''; }
        return 'gcm:' . base64_encode($iv . $tag . $cipher);
    }

    /**
     * Decrypt ciphertext produced by encrypt().
     */
    public static function decrypt(string $ciphertext): string {
        if ($ciphertext === '') { return ''; }

        $key = self::key();
        if (strpos($ciphertext, 'sbx:') === 0 && function_exists('sodium_crypto_secretbox_open')) {
            $blob = base64_decode(substr($ciphertext, 4), true);
            if (!is_string($blob) || strlen($blob) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) { return ''; }
            $nonce = substr($blob, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = substr($blob, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
            return is_string($plain) ? $plain : '';
        }

        if (strpos($ciphertext, 'gcm:') === 0) {
            $blob = base64_decode(substr($ciphertext, 4), true);
            if (!is_string($blob) || strlen($blob) < (12 + 16)) { return ''; }
            $iv = substr($blob, 0, 12);
            $tag = substr($blob, 12, 16);
            $cipher = substr($blob, 28);
            $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            return is_string($plain) ? $plain : '';
        }

        // Unknown format: treat as plaintext (back-compat)
        return $ciphertext;
    }

    private static function key(): string {
        $stored = (string) get_option(self::OPT_KEY, '');
        if ($stored && strlen($stored) >= 32) {
            $raw = base64_decode($stored, true);
            if (is_string($raw) && strlen($raw) >= 32) {
                return substr($raw, 0, 32);
            }
        }
        $key = self::derive();
        update_option(self::OPT_KEY, base64_encode($key), true);
        return $key;
    }

    private static function derive(): string {
        $material = (string) (defined('AUTH_SALT') ? AUTH_SALT : '') . '|' . (string) (defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : '') . '|' . site_url();
        return substr(hash('sha256', $material, true), 0, 32);
    }
}
