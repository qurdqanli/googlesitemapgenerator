<?php
namespace WPNexusAI\Utils;

if (!defined('ABSPATH')) { exit; }

final class Arr {

    public static function get(array $arr, string $key, $default = null) {
        if ($key === '') { return $default; }
        if (array_key_exists($key, $arr)) { return $arr[$key]; }
        return $default;
    }

    public static function bool(array $arr, string $key, bool $default = false): bool {
        $v = self::get($arr, $key, null);
        if ($v === null) { return $default; }
        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }

    public static function int(array $arr, string $key, int $default = 0): int {
        $v = self::get($arr, $key, null);
        if ($v === null) { return $default; }
        return (int) $v;
    }

    public static function str(array $arr, string $key, string $default = ''): string {
        $v = self::get($arr, $key, null);
        if ($v === null) { return $default; }
        return is_scalar($v) ? (string) $v : $default;
    }

    /** @return array<int, int> */
    public static function int_list($value): array {
        if (!is_array($value)) { return []; }
        $out = [];
        foreach ($value as $v) {
            $i = (int) $v;
            if ($i > 0) { $out[] = $i; }
        }
        return array_values(array_unique($out));
    }
}
