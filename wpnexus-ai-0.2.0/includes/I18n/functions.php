<?php
namespace WPNexusAI\I18n;

if (!defined('ABSPATH')) { exit; }

if (!function_exists('t')) {
    /**
     * Global helper. We guard with function_exists to avoid conflicts.
     *
     * @param string $key
     * @param array<int, mixed> $args
     */
    function t(string $key, array $args = []): string {
        return I18n::instance()->t($key, $args);
    }
}
