<?php
namespace WPNexusAI\Settings;

if (!defined('ABSPATH')) { exit; }

final class SettingsStore {

    private const OPT = 'wpnexus_ai_settings';

    /** @return array<string, mixed> */
    public static function all(): array {
        $v = get_option(self::OPT, []);
        return is_array($v) ? $v : [];
    }

    /** @param array<string, mixed> $data */
    public static function save(array $data): void {
        update_option(self::OPT, $data, true);
    }

    public static function get(string $key, $default = null) {
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function str(string $key, string $default = ''): string {
        $v = self::get($key, null);
        return is_scalar($v) ? (string) $v : $default;
    }

    public static function bool(string $key, bool $default = false): bool {
        $v = self::get($key, null);
        if ($v === null) { return $default; }
        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }
}
