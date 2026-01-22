<?php
namespace WPNexusAI\Admin\Screens;

use WPNexusAI\Keys\KeysRepo;
use WPNexusAI\Providers\ProviderRegistry;

if (!defined('ABSPATH')) { exit; }

final class KeysScreen implements ScreenInterface {

    /** @var KeysRepo */
    private $repo;

    public function __construct() {
        $this->repo = new KeysRepo();

        add_action('admin_post_wpnexus_ai_save_key', [$this, 'handle_save']);
        add_action('admin_post_wpnexus_ai_delete_key', [$this, 'handle_delete']);
    }

    public function render(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }

        $sub = isset($_GET['sub']) ? sanitize_text_field((string) $_GET['sub']) : '';

        echo '<div class="wrap"><h1>AI Keys</h1>';

        if ($sub === 'edit') {
            $this->render_form((int) ($_GET['id'] ?? 0));
        } else {
            echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=wpnexus-ai-keys&sub=edit')) . '">Add Key</a></p>';
            $this->render_list();
        }

        echo '</div>';
    }

    private function render_list(): void {
        $rows = $this->repo->all(false);

        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Provider</th><th>Model</th><th>Label</th><th>Active</th><th>Actions</th></tr></thead><tbody>';

        foreach ($rows as $r) {
            $edit = admin_url('admin.php?page=wpnexus-ai-keys&sub=edit&id=' . (int) $r['id']);
            $del = wp_nonce_url(admin_url('admin-post.php?action=wpnexus_ai_delete_key&id=' . (int) $r['id']), 'wpnexus_ai_delete_key_' . (int) $r['id']);

            echo '<tr>';
            echo '<td>' . esc_html((string) $r['id']) . '</td>';
            echo '<td>' . esc_html((string) $r['provider']) . '</td>';
            echo '<td>' . esc_html((string) ($r['model'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) $r['label']) . '</td>';
            echo '<td>' . ((int) $r['is_active'] === 1 ? 'Yes' : 'No') . '</td>';
            echo '<td><a class="button" href="' . esc_url($edit) . '">Edit</a> ';
            echo '<a class="button button-link-delete" href="' . esc_url($del) . '">Delete</a></td>';
            echo '</tr>';
        }

        if (!$rows) {
            echo '<tr><td colspan="6">No keys yet.</td></tr>';
        }

        echo '</tbody></table>';

        echo '<p style="margin-top:12px;"><em>Tip:</em> If Model is empty, the provider default from Settings will be used.</p>';
    }

    private function render_form(int $id): void {
        $said = $id > 0 ? $this->repo->get($id) : null;

        $provider = $said ? (string) $said['provider'] : 'openai';
        $model = $said ? (string) ($said['model'] ?? '') : '';
        $label = $said ? (string) $said['label'] : '';
        $active = $said ? ((int) $said['is_active'] === 1) : true;

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="wpnexus_ai_save_key" />';
        wp_nonce_field('wpnexus_ai_save_key');

        if ($id > 0) {
            echo '<input type="hidden" name="id" value="' . esc_attr((string) $id) . '">';
        }

        echo '<table class="form-table"><tbody>';

        echo '<tr><th><label>Provider</label></th><td><select name="provider">';
        foreach (ProviderRegistry::choices(false) as $k => $lab) {
            echo '<option value="' . esc_attr($k) . '" ' . selected($provider, $k, false) . '>' . esc_html($lab) . '</option>';
        }
        echo '</select></td></tr>';

        $hint = ProviderRegistry::providers()[$provider]['model_hint'] ?? '';
        echo '<tr><th><label>Model</label></th><td><input class="regular-text" name="model" value="' . esc_attr($model) . '" placeholder="' . esc_attr((string) $hint) . '"></td></tr>';
        echo '<tr><th><label>Label</label></th><td><input class="regular-text" name="label" value="' . esc_attr($label) . '" required></td></tr>';
        echo '<tr><th><label>API Key</label></th><td><input class="regular-text" name="key" value="" placeholder="' . ($id > 0 ? 'Leave empty to keep existing' : '') . '"></td></tr>';
        echo '<tr><th><label>Active</label></th><td><label><input type="checkbox" name="is_active" value="1" ' . checked($active, true, false) . '> Enabled</label></td></tr>';

        echo '</tbody></table>';

        submit_button($id > 0 ? 'Update Key' : 'Add Key');

        echo '<p><a href="' . esc_url(admin_url('admin.php?page=wpnexus-ai-keys')) . '">&larr; Back to list</a></p>';
        echo '</form>';
    }

    public function handle_save(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
        check_admin_referer('wpnexus_ai_save_key');

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $provider = sanitize_text_field((string) ($_POST['provider'] ?? 'openai'));
        $label = sanitize_text_field((string) ($_POST['label'] ?? ''));
        $model = sanitize_text_field((string) ($_POST['model'] ?? ''));
        $key = (string) ($_POST['key'] ?? '');
        $is_active = !empty($_POST['is_active']);

        if (!ProviderRegistry::is_valid($provider) || $provider === 'auto') {
            $provider = 'openai';
        }

        if ($id > 0) {
            $this->repo->update($id, $provider, $label, $model, $key, $is_active);
        } else {
            $this->repo->insert($provider, $label, $model, $key, $is_active);
        }

        wp_safe_redirect(admin_url('admin.php?page=wpnexus-ai-keys'));
        exit;
    }

    public function handle_delete(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
        $id = (int) ($_GET['id'] ?? 0);
        check_admin_referer('wpnexus_ai_delete_key_' . $id);

        if ($id > 0) {
            $this->repo->delete($id);
        }

        wp_safe_redirect(admin_url('admin.php?page=wpnexus-ai-keys'));
        exit;
    }
}

