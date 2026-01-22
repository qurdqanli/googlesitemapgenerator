<?php
namespace WPNexusAI\Targets;

use WPNexusAI\Utils\Crypto;

if (!defined('ABSPATH')) { exit; }

final class TargetRepo {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'wpnexus_ai_targets';
    }

    /** @return array<int, array<string, mixed>> */
    public function all(bool $active_only = false): array {
        global $wpdb;
        $sql = "SELECT * FROM {$this->table}";
        if ($active_only) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY id DESC";
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    public function get(int $id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function insert(string $name, string $base_url, string $token_plain, bool $is_active, array $settings = []): int {
        global $wpdb;
        $now = current_time('mysql', true);
        $wpdb->insert($this->table, [
            'name' => $name,
            'base_url' => rtrim($base_url, '/'),
            'token_enc' => Crypto::encrypt($token_plain),
            'is_active' => $is_active ? 1 : 0,
            'settings_json' => wp_json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $wpdb->insert_id;
    }

    public function update(int $id, string $name, string $base_url, string $token_plain_or_empty, bool $is_active, array $settings = []): bool {
        global $wpdb;
        $data = [
            'name' => $name,
            'base_url' => rtrim($base_url, '/'),
            'is_active' => $is_active ? 1 : 0,
            'settings_json' => wp_json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => current_time('mysql', true),
        ];
        if ($token_plain_or_empty !== '') {
            $data['token_enc'] = Crypto::encrypt($token_plain_or_empty);
        }
        $wpdb->update($this->table, $data, ['id' => $id]);
        return $wpdb->rows_affected >= 0;
    }

    public function delete(int $id): bool {
        global $wpdb;
        $wpdb->delete($this->table, ['id' => $id]);
        return $wpdb->rows_affected > 0;
    }

    public function token_plain(array $row): string {
        $enc = isset($row['token_enc']) ? (string) $row['token_enc'] : '';
        return Crypto::decrypt($enc);
    }

    /** @return array<string, mixed> */
    public function settings(array $row): array {
        $raw = isset($row['settings_json']) ? (string) $row['settings_json'] : '';
        $decoded = $raw ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : [];
    }
}
