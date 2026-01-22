<?php
namespace WPNexusAI\Rules;

use WPNexusAI\Utils\Arr;

if (!defined('ABSPATH')) { exit; }

final class RulesRepo {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'wpnexus_ai_rules';
    }

    /** @return array<int, array<string, mixed>> */
    public function all(bool $enabled_only = false): array {
        global $wpdb;
        $sql = "SELECT * FROM {$this->table}";
        if ($enabled_only) {
            $sql .= " WHERE is_enabled = 1";
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

    /** @param array<string, mixed> $data */
    public function insert(array $data): int {
        global $wpdb;
        $now = current_time('mysql', true);
        $row = $this->normalize($data);
        $row['created_at'] = $now;
        $row['updated_at'] = $now;
        $wpdb->insert($this->table, $row);
        return (int) $wpdb->insert_id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool {
        global $wpdb;
        $row = $this->normalize($data);
        $row['updated_at'] = current_time('mysql', true);
        $wpdb->update($this->table, $row, ['id' => $id]);
        return $wpdb->rows_affected >= 0;
    }

    public function delete(int $id): bool {
        global $wpdb;
        $wpdb->delete($this->table, ['id' => $id]);
        return $wpdb->rows_affected > 0;
    }

    /** @return array<string, mixed> */
    private function normalize(array $data): array {
        $source_post_types = Arr::str($data, 'source_post_types', 'post');
        $trigger_statuses = Arr::str($data, 'trigger_statuses', 'publish');

        return [
            'name' => Arr::str($data, 'name', 'Rule'),
            'is_enabled' => Arr::bool($data, 'is_enabled', true) ? 1 : 0,
            'source_post_types' => $source_post_types,
            'source_taxonomy' => Arr::str($data, 'source_taxonomy', ''),
            'source_term_ids_json' => wp_json_encode(Arr::get($data, 'source_term_ids', []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'source_author_ids_json' => wp_json_encode(Arr::get($data, 'source_author_ids', []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'trigger_statuses' => $trigger_statuses,
            'target_ids_json' => wp_json_encode(Arr::get($data, 'target_ids', []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'target_category_map_json' => wp_json_encode(Arr::get($data, 'target_category_map', new \stdClass()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'translate_taxonomies' => Arr::bool($data, 'translate_taxonomies', true) ? 1 : 0,
            'persona' => Arr::str($data, 'persona', 'neutral'),
            'custom_prompt' => Arr::str($data, 'custom_prompt', ''),
            'internal_links_json' => wp_json_encode(Arr::get($data, 'internal_links', []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'image_mode' => Arr::str($data, 'image_mode', 'keep'),
            'image_prompt' => Arr::str($data, 'image_prompt', ''),
            'meta_map_json' => wp_json_encode(Arr::get($data, 'meta_map', []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'translate_meta' => Arr::bool($data, 'translate_meta', false) ? 1 : 0,
            'acf_map_json' => wp_json_encode(Arr::get($data, 'acf_map', []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    /** @return array<int, int> */
    public function source_term_ids(array $row): array {
        $raw = (string) ($row['source_term_ids_json'] ?? '');
        $decoded = $raw ? json_decode($raw, true) : null;
        return Arr::int_list($decoded);
    }

    /** @return array<int, int> */
    public function source_author_ids(array $row): array {
        $raw = (string) ($row['source_author_ids_json'] ?? '');
        $decoded = $raw ? json_decode($raw, true) : null;
        return Arr::int_list($decoded);
    }

    /** @return array<int, int> */
    public function target_ids(array $row): array {
        $raw = (string) ($row['target_ids_json'] ?? '');
        $decoded = $raw ? json_decode($raw, true) : null;
        return Arr::int_list($decoded);
    }

    /** @return array<string, mixed> */
    public function category_map(array $row): array {
        $raw = (string) ($row['target_category_map_json'] ?? '');
        $decoded = $raw ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<int, array<string, string>> */
    public function internal_links(array $row): array {
        $raw = (string) ($row['internal_links_json'] ?? '');
        $decoded = $raw ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<int, array<string, string>> */
    public function meta_map(array $row): array {
        $raw = (string) ($row['meta_map_json'] ?? '');
        $decoded = $raw ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<int, array<string, string>> */
    public function acf_map(array $row): array {
        $raw = (string) ($row['acf_map_json'] ?? '');
        $decoded = $raw ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : [];
    }
}
