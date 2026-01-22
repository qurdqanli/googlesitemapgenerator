<?php
namespace WPNexusAI\Keys;

use WPNexusAI\Utils\Crypto;

if (!defined('ABSPATH')) { exit; }

final class KeysRepo {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'wpnexus_ai_keys';
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

    public function insert(string $provider, string $label, string $model, string $key_plain, bool $is_active): int {
        global $wpdb;
        $now = current_time('mysql', true);
        $wpdb->insert($this->table, [
            'provider' => $provider,
            'label' => $label,
            'model' => $model,
            'key_enc' => Crypto::encrypt($key_plain),
            'is_active' => $is_active ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $wpdb->insert_id;
    }

    public function update(int $id, string $provider, string $label, string $model, string $key_plain_or_empty, bool $is_active): bool {
        global $wpdb;
        $data = [
            'provider' => $provider,
            'label' => $label,
            'model' => $model,
            'is_active' => $is_active ? 1 : 0,
            'updated_at' => current_time('mysql', true),
        ];
        if ($key_plain_or_empty !== '') {
            $data['key_enc'] = Crypto::encrypt($key_plain_or_empty);
        }
        $wpdb->update($this->table, $data, ['id' => $id]);
        return $wpdb->rows_affected >= 0;
    }

    public function delete(int $id): bool {
        global $wpdb;
        $wpdb->delete($this->table, ['id' => $id]);
        return $wpdb->rows_affected > 0;
    }

    public function key_plain(array $row): string {
        return Crypto::decrypt((string) ($row['key_enc'] ?? ''));
    }
}

