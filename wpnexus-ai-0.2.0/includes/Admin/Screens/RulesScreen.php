<?php
namespace WPNexusAI\Admin\Screens;

use WPNexusAI\Rules\RulesRepo;
use WPNexusAI\Targets\TargetRepo;

if (!defined('ABSPATH')) { exit; }

final class RulesScreen implements ScreenInterface {

    /** @var RulesRepo */
    private $repo;

    /** @var TargetRepo */
    private $targets;

    public function __construct() {
        $this->repo = new RulesRepo();
        $this->targets = new TargetRepo();

        add_action('admin_post_wpnexus_ai_save_rule', [$this, 'handle_save']);
        add_action('admin_post_wpnexus_ai_delete_rule', [$this, 'handle_delete']);
    }

    public function render(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }

        $sub = isset($_GET['sub']) ? sanitize_text_field((string) $_GET['sub']) : '';

        echo '<div class="wrap"><h1>Rules (Auto-Pilot)</h1>';

        if ($sub === 'edit') {
            $this->render_form((int) ($_GET['id'] ?? 0));
        } else {
            echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=wpnexus-ai-rules&sub=edit')) . '">Add Rule</a></p>';
            $this->render_list();
            echo '<p style="margin-top:12px;"><em>Tip:</em> Add <code>publish_update</code> to trigger on updates.</p>';
        }

        echo '</div>';
    }

    private function render_list(): void {
        $rows = $this->repo->all(false);

        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Name</th><th>Enabled</th><th>Post Types</th><th>Triggers</th><th>Targets</th><th>Actions</th></tr></thead><tbody>';

        foreach ($rows as $r) {
            $edit = admin_url('admin.php?page=wpnexus-ai-rules&sub=edit&id=' . (int) $r['id']);
            $del = wp_nonce_url(admin_url('admin-post.php?action=wpnexus_ai_delete_rule&id=' . (int) $r['id']), 'wpnexus_ai_delete_rule_' . (int) $r['id']);

            $targets = implode(',', $this->repo->target_ids($r));

            echo '<tr>';
            echo '<td>' . esc_html((string) $r['id']) . '</td>';
            echo '<td>' . esc_html((string) $r['name']) . '</td>';
            echo '<td>' . ((int) $r['is_enabled'] === 1 ? 'Yes' : 'No') . '</td>';
            echo '<td>' . esc_html((string) $r['source_post_types']) . '</td>';
            echo '<td>' . esc_html((string) $r['trigger_statuses']) . '</td>';
            echo '<td>' . esc_html($targets) . '</td>';
            echo '<td><a class="button" href="' . esc_url($edit) . '">Edit</a> ';
            echo '<a class="button button-link-delete" href="' . esc_url($del) . '">Delete</a></td>';
            echo '</tr>';
        }

        if (!$rows) {
            echo '<tr><td colspan="7">No rules yet.</td></tr>';
        }

        echo '</tbody></table>';
    }

    private function render_form(int $id): void {
        $row = $id > 0 ? $this->repo->get($id) : null;

        $name = $row ? (string) $row['name'] : '';
        $enabled = $row ? ((int) $row['is_enabled'] === 1) : true;
        $source_post_types = $row ? (string) $row['source_post_types'] : 'post';
        $source_taxonomy = $row ? (string) $row['source_taxonomy'] : 'category';
        $source_terms = $row ? implode(',', $this->repo->source_term_ids($row)) : '';
        $source_authors = $row ? implode(',', $this->repo->source_author_ids($row)) : '';
        $trigger_statuses = $row ? (string) $row['trigger_statuses'] : 'publish,publish_update';
        $target_ids = $row ? $this->repo->target_ids($row) : [];
        $translate_taxonomies = $row ? ((int) $row['translate_taxonomies'] === 1) : true;
        $persona = $row ? (string) $row['persona'] : 'neutral';
        $custom_prompt = $row ? (string) $row['custom_prompt'] : '';

        $image_mode = $row ? (string) $row['image_mode'] : 'keep';
        $image_prompt = $row ? (string) $row['image_prompt'] : '';

        $cat_map = $row ? $this->repo->category_map($row) : [];
        $links = $row ? $this->repo->internal_links($row) : [];
        $meta_map = $row ? $this->repo->meta_map($row) : [];
        $acf_map = $row ? $this->repo->acf_map($row) : [];
        $translate_meta = $row ? ((int) $row['translate_meta'] === 1) : false;

        $all_targets = $this->targets->all(false);

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="wpnexus_ai_save_rule" />';
        wp_nonce_field('wpnexus_ai_save_rule');

        if ($id > 0) {
            echo '<input type="hidden" name="id" value="' . esc_attr((string) $id) . '">';
        }

        echo '<table class="form-table"><tbody>';

        echo '<tr><th><label>Name</label></th><td><input class="regular-text" name="name" value="' . esc_attr($name) . '" required></td></tr>';
        echo '<tr><th><label>Enabled</label></th><td><label><input type="checkbox" name="is_enabled" value="1" ' . checked($enabled, true, false) . '> Enabled</label></td></tr>';

        echo '<tr><th><label>Source Post Types</label></th><td><input class="regular-text" name="source_post_types" value="' . esc_attr($source_post_types) . '" placeholder="post,product"></td></tr>';

        echo '<tr><th><label>Trigger Statuses</label></th><td><input class="regular-text" name="trigger_statuses" value="' . esc_attr($trigger_statuses) . '" placeholder="publish,publish_update"></td></tr>';

        echo '<tr><th><label>Filter Taxonomy</label></th><td><input class="regular-text" name="source_taxonomy" value="' . esc_attr($source_taxonomy) . '" placeholder="category"></td></tr>';
        echo '<tr><th><label>Filter Term IDs</label></th><td><input class="regular-text" name="source_term_ids" value="' . esc_attr($source_terms) . '" placeholder="12,34"></td></tr>';

        echo '<tr><th><label>Filter Author IDs</label></th><td><input class="regular-text" name="source_author_ids" value="' . esc_attr($source_authors) . '" placeholder="1,2"></td></tr>';

        echo '<tr><th><label>Targets</label></th><td>';
        if ($all_targets) {
            foreach ($all_targets as $t) {
                $tid = (int) $t['id'];
                $checked = in_array($tid, $target_ids, true);
                echo '<label style="display:block; margin-bottom:6px;">';
                echo '<input type="checkbox" name="target_ids[]" value="' . esc_attr((string) $tid) . '" ' . checked($checked, true, false) . '> ';
                echo esc_html((string) $t['name']) . ' (' . esc_html((string) $t['base_url']) . ')';
                echo '</label>';
            }
        } else {
            echo '<em>No targets yet. Add one first.</em>';
        }
        echo '</td></tr>';

        echo '<tr><th><label>Translate Taxonomies</label></th><td><label><input type="checkbox" name="translate_taxonomies" value="1" ' . checked($translate_taxonomies, true, false) . '> Translate categories/tags (default: ON)</label></td></tr>';

        echo '<tr><th><label>Category Mapping</label></th><td>';
        echo '<textarea name="target_category_map_json" class="large-text code" rows="5" placeholder=\'{"22":"Повестка дня","15":"Спорт"}\'>' . esc_textarea(wp_json_encode($cat_map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</textarea>';
        echo '<p class="description">Optional. Map source term_id to exact target category name. Overrides translation.</p>';
        echo '</td></tr>';

        echo '<tr><th><label>Persona</label></th><td><select name="persona">';
        foreach (['neutral','formal','funny','sales','story'] as $p) {
            echo '<option value="' . esc_attr($p) . '" ' . selected($persona, $p, false) . '>' . esc_html($p) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th><label>Custom Prompt</label></th><td><textarea name="custom_prompt" class="large-text" rows="4" placeholder="Extra instruction for translation/rewrite...">' . esc_textarea($custom_prompt) . '</textarea></td></tr>';

        echo '<tr><th><label>Internal Links JSON</label></th><td>';
        echo '<textarea name="internal_links_json" class="large-text code" rows="5" placeholder=\'[{"keyword":"WordPress","url":"https://example.com","max":1,"nofollow":1}]\'>' . esc_textarea(wp_json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</textarea>';
        echo '</td></tr>';

        echo '<tr><th><label>Image Mode</label></th><td><select name="image_mode">';
        foreach (['keep' => 'Keep source featured image', 'generate' => 'Generate new (OpenAI)', 'none' => 'No featured image'] as $k => $label) {
            echo '<option value="' . esc_attr($k) . '" ' . selected($image_mode, $k, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th><label>Image Prompt</label></th><td><textarea name="image_prompt" class="large-text" rows="3" placeholder="Optional. If empty, prompt is derived from content.">' . esc_textarea($image_prompt) . '</textarea></td></tr>';

        echo '<tr><th><label>Translate Meta</label></th><td><label><input type="checkbox" name="translate_meta" value="1" ' . checked($translate_meta, true, false) . '> Translate mapped meta values</label></td></tr>';

        echo '<tr><th><label>Meta Map JSON</label></th><td>';
        echo '<textarea name="meta_map_json" class="large-text code" rows="5" placeholder=\'[{"source":"_my_meta","target":"_my_meta"}]\'>' . esc_textarea(wp_json_encode($meta_map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</textarea>';
        echo '</td></tr>';

        echo '<tr><th><label>ACF Map JSON</label></th><td>';
        echo '<textarea name="acf_map_json" class="large-text code" rows="5" placeholder=\'[{"source":"field_123","target":"field_abc"}]\'>' . esc_textarea(wp_json_encode($acf_map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</textarea>';
        echo '</td></tr>';

        echo '</tbody></table>';

        submit_button($id > 0 ? 'Update Rule' : 'Add Rule');

        echo '<p><a href="' . esc_url(admin_url('admin.php?page=wpnexus-ai-rules')) . '">&larr; Back to list</a></p>';
        echo '</form>';
    }

    public function handle_save(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
        check_admin_referer('wpnexus_ai_save_rule');

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        $name = sanitize_text_field((string) ($_POST['name'] ?? 'Rule'));
        $is_enabled = !empty($_POST['is_enabled']);

        $source_post_types = sanitize_text_field((string) ($_POST['source_post_types'] ?? 'post'));
        $trigger_statuses = sanitize_text_field((string) ($_POST['trigger_statuses'] ?? 'publish,publish_update'));

        $source_taxonomy = sanitize_text_field((string) ($_POST['source_taxonomy'] ?? 'category'));
        $source_term_ids = $this->csv_to_ints((string) ($_POST['source_term_ids'] ?? ''));

        $source_author_ids = $this->csv_to_ints((string) ($_POST['source_author_ids'] ?? ''));

        $target_ids = isset($_POST['target_ids']) && is_array($_POST['target_ids']) ? array_map('intval', $_POST['target_ids']) : [];

        $translate_taxonomies = !empty($_POST['translate_taxonomies']);
        $persona = sanitize_text_field((string) ($_POST['persona'] ?? 'neutral'));
        $custom_prompt = (string) ($_POST['custom_prompt'] ?? '');

        $image_mode = sanitize_text_field((string) ($_POST['image_mode'] ?? 'keep'));
        $image_prompt = (string) ($_POST['image_prompt'] ?? '');

        $translate_meta = !empty($_POST['translate_meta']);

        $cat_map = $this->json_decode_assoc((string) ($_POST['target_category_map_json'] ?? ''));
        $links = $this->json_decode_array((string) ($_POST['internal_links_json'] ?? ''));
        $meta_map = $this->json_decode_array((string) ($_POST['meta_map_json'] ?? ''));
        $acf_map = $this->json_decode_array((string) ($_POST['acf_map_json'] ?? ''));

        $data = [
            'name' => $name,
            'is_enabled' => $is_enabled,
            'source_post_types' => $source_post_types,
            'source_taxonomy' => $source_taxonomy,
            'source_term_ids' => $source_term_ids,
            'source_author_ids' => $source_author_ids,
            'trigger_statuses' => $trigger_statuses,
            'target_ids' => $target_ids,
            'target_category_map' => $cat_map,
            'translate_taxonomies' => $translate_taxonomies,
            'persona' => $persona,
            'custom_prompt' => $custom_prompt,
            'internal_links' => $links,
            'image_mode' => $image_mode,
            'image_prompt' => $image_prompt,
            'meta_map' => $meta_map,
            'translate_meta' => $translate_meta,
            'acf_map' => $acf_map,
        ];

        if ($id > 0) {
            $this->repo->update($id, $data);
        } else {
            $this->repo->insert($data);
        }

        wp_safe_redirect(admin_url('admin.php?page=wpnexus-ai-rules'));
        exit;
    }

    public function handle_delete(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
        $id = (int) ($_GET['id'] ?? 0);
        check_admin_referer('wpnexus_ai_delete_rule_' . $id);

        if ($id > 0) {
            $this->repo->delete($id);
        }

        wp_safe_redirect(admin_url('admin.php?page=wpnexus-ai-rules'));
        exit;
    }

    /** @return array<int, int> */
    private function csv_to_ints(string $csv): array {
        $csv = trim($csv);
        if ($csv === '') { return []; }
        $parts = array_map('trim', explode(',', $csv));
        $out = [];
        foreach ($parts as $p) {
            if ($p === '') { continue; }
            $out[] = (int) $p;
        }
        return array_values(array_unique(array_filter($out)));
    }

    /** @return array<string, mixed> */
    private function json_decode_assoc(string $raw): array {
        $raw = trim($raw);
        if ($raw === '') { return []; }
        $d = json_decode($raw, true);
        return is_array($d) ? $d : [];
    }

    /** @return array<int, mixed> */
    private function json_decode_array(string $raw): array {
        $raw = trim($raw);
        if ($raw === '') { return []; }
        $d = json_decode($raw, true);
        return is_array($d) ? $d : [];
    }
}
