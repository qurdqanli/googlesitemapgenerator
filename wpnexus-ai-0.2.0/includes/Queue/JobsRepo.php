<?php
namespace WPNexusAI\Queue;

use WPNexusAI\Utils\Arr;

if (!defined('ABSPATH')) { exit; }

final class JobsRepo {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'wpnexus_ai_jobs';
    }

    public function insert(string $type, array $payload, ?int $as_action_id = null, ?string $scheduled_at_gmt = null): int {
        global $wpdb;
        $now = current_time('mysql', true);
        $wpdb->insert($this->table, [
            'type' => $type,
            'status' => 'pending',
            'payload_json' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'attempts' => 0,
            'last_error' => null,
            'scheduled_at' => $scheduled_at_gmt,
            'as_action_id' => $as_action_id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $wpdb->insert_id;
    }

    public function set_status(int $id, string $status, string $last_error = ''): void {
        global $wpdb;
        $wpdb->update($this->table, [
            'status' => $status,
            'last_error' => $last_error !== '' ? $last_error : null,
            'updated_at' => current_time('mysql', true),
        ], ['id' => $id]);
    }

    public function inc_attempts(int $id): void {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table} SET attempts = attempts + 1, updated_at = %s WHERE id = %d",
            current_time('mysql', true),
            $id
        ));
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 50): array {
        global $wpdb;
        $limit = max(1, min(200, $limit));
        $rows = $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY id DESC LIMIT {$limit}", ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /** @return array<int, int> */
    public function due_pending_ids(int $limit = 10): array {
        global $wpdb;
        $limit = max(1, min(50, $limit));
        $now = current_time('mysql', true);
        $sql = $wpdb->prepare(
            "SELECT id FROM {$this->table} 
             WHERE status = 'pending' 
               AND (scheduled_at IS NULL OR scheduled_at <= %s)
             ORDER BY id ASC
             LIMIT {$limit}",
            $now
        );
        $rows = $wpdb->get_col($sql);
        $out = [];
        if (is_array($rows)) {
            foreach ($rows as $v) {
                $out[] = (int) $v;
            }
        }
        return $out;
    }

    /** @return array<string, mixed> */
    
    public function reset(int $id): void {
        global $wpdb;
        $wpdb->update($this->table, [
            'status' => 'pending',
            'last_error' => null,
            'updated_at' => current_time('mysql', true),
        ], ['id' => $id]);
    }

public function payload(array $row): array {
        $raw = (string) ($row['payload_json'] ?? '');
        $decoded = $raw ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : [];
    }
}
