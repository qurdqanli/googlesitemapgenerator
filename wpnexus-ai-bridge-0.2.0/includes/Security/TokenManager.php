<?php
namespace WPNexusAIBridge\Security;

if (!defined('ABSPATH')) { exit; }

final class TokenManager {

    private const OPT = 'wpnexus_ai_bridge_token';

    public function get(): string {
        $t = get_option(self::OPT, '');
        return is_string($t) ? $t : '';
    }

    public function ensure(): string {
        $t = $this->get();
        if ($t === '') {
            $t = $this->generate();
            update_option(self::OPT, $t, true);
        }
        return $t;
    }

    public function rotate(): string {
        $t = $this->generate();
        update_option(self::OPT, $t, true);
        return $t;
    }

    private function generate(): string {
        return wp_generate_password(48, false, false);
    }

    /**
     * Very simple Bearer token check.
     */
    public function authorize_request(): bool {
        $header = '';
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = (string) $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $h = apache_request_headers();
            if (is_array($h) && isset($h['Authorization'])) {
                $header = (string) $h['Authorization'];
            }
        }

        $header = trim($header);
        if (stripos($header, 'Bearer ') !== 0) {
            return false;
        }

        $token = trim(substr($header, 7));
        $expected = $this->get();

        if ($expected === '') { return false; }

        return hash_equals($expected, $token);
    }
}
