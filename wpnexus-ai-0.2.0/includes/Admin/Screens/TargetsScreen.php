<?php
namespace WPNexusAI\Admin\Screens;

use WPNexusAI\Bridge\BridgeClient;
use WPNexusAI\Targets\TargetRepo;
use WPNexusAI\I18n\I18n;

if (!defined('ABSPATH')) { exit; }

final class TargetsScreen implements ScreenInterface {

    /** @var TargetRepo */
    private $repo;

    public function __construct() {
        $this->repo = new TargetRepo();

        add_action('admin_post_wpnexus_ai_save_target', [$this, 'handle_save']);
        add_action('admin_post_wpnexus_ai_delete_target', [$this, 'handle_delete']);
        add_action('admin_post_wpnexus_ai_test_target', [$this, 'handle_test']);
    }

    public function render(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }

        $action = isset($_GET['sub']) ? sanitize_text_field((string) $_GET['sub']) : '';

        echo '<div class="wrap"><h1>Targets</h1>';

        if ($action === 'edit') {
            $this->render_form((int) ($_GET['id'] ?? 0));
        } else {
            echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=wpnexus-ai-targets&sub=edit')) . '">Add Target</a></p>';
            $this->render_list();
        }

        echo '</div>';
    }

    private function render_list(): void {
        $rows = $this->repo->all(false);

        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Name</th><th>Base URL</th><th>Lang</th><th>Active</th><th>Actions</th></tr></thead><tbody>';

        foreach ($rows as $r) {
            $settings = $this->repo->settings($r);
            $lang = isset($settings['lang']) ? (string) $settings['lang'] : '';
            $edit = admin_url('admin.php?page=wpnexus-ai-targets&sub=edit&id=' . (int) $r['id']);
            $del = wp_nonce_url(admin_url('admin-post.php?action=wpnexus_ai_delete_target&id=' . (int) $r['id']), 'wpnexus_ai_delete_target_' . (int) $r['id']);
            $test = wp_nonce_url(admin_url('admin-post.php?action=wpnexus_ai_test_target&id=' . (int) $r['id']), 'wpnexus_ai_test_target_' . (int) $r['id']);

            echo '<tr>';
            echo '<td>' . esc_html((string) $r['id']) . '</td>';
            echo '<td>' . esc_html((string) $r['name']) . '</td>';
            echo '<td>' . esc_html((string) $r['base_url']) . '</td>';
            echo '<td>' . esc_html($lang) . '</td>';
            echo '<td>' . ((int) $r['is_active'] === 1 ? 'Yes' : 'No') . '</td>';
            echo '<td><a class="button" href="' . esc_url($edit) . '">Edit</a> ';
            echo '<a class="button" href="' . esc_url($test) . '">Test</a> ';
            echo '<a class="button button-link-delete" href="' . esc_url($del) . '">Delete</a></td>';
            echo '</tr>';
        }

        if (!$rows) {
            echo '<tr><td colspan="6">No targets yet.</td></tr>';
        }

        echo '</tbody></table>';
    }

    private function render_form(int $id): void {
        $row = $id > 0 ? $this->repo->get($id) : null;
        $settings = $row ? $this->repo->settings($row) : [];

        $name = $row ? (string) $row['name'] : '';
        $base_url = $row ? (string) $row['base_url'] : '';
        $lang = isset($settings['lang']) ? (string) $settings['lang'] : '';
        $active = $row ? ((int) $row['is_active'] === 1) : true;

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="wpnexus_ai_save_target" />';
        wp_nonce_field('wpnexus_ai_save_target');

        if ($id > 0) {
            echo '<input type="hidden" name="id" value="' . esc_attr((string) $id) . '">';
        }

        echo '<table class="form-table"><tbody>';

        echo '<tr><th><label>Name</label></th><td><input class="regular-text" name="name" value="' . esc_attr($name) . '" required></td></tr>';
        echo '<tr><th><label>Base URL</label></th><td><input class="regular-text" name="base_url" value="' . esc_attr($base_url) . '" placeholder="https://target-site.com" required></td></tr>';
        echo '<tr><th><label>Bridge Token</label></th><td><input class="regular-text" name="token" value="" placeholder="' . ($id > 0 ? 'Leave empty to keep existing' : '') . '"></td></tr>';
        echo '<tr><th><label>Target Lang</label></th><td><input class="regular-text" name="lang" value="' . esc_attr($lang) . '" placeholder="ru / az / en"></td></tr>';
        echo '<tr><th><label>Active</label></th><td><label><input type="checkbox" name="is_active" value="1" ' . checked($active, true, false) . '> Enabled</label></td></tr>';

        echo '</tbody></table>';

        submit_button($id > 0 ? 'Update Target' : 'Add Target');

        echo '<p><a href="' . esc_url(admin_url('admin.php?page=wpnexus-ai-targets')) . '">&larr; Back to list</a></p>';
        echo '</form>';
    }

    public function handle_save(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
        check_admin_referer('wpnexus_ai_save_target');

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name = sanitize_text_field((string) ($_POST['name'] ?? ''));
        $base_url = esc_url_raw((string) ($_POST['base_url'] ?? ''));
        $token = (string) ($_POST['token'] ?? '');
        $lang = sanitize_text_field((string) ($_POST['lang'] ?? ''));
        $is_active = !empty($_POST['is_active']);

        $settings = ['lang' => $lang];

        if ($id > 0) {
            $this->repo->update($id, $name, $base_url, $token, $is_active, $settings);
        } else {
            $this->repo->insert($name, $base_url, $token, $is_active, $settings);
        }

        wp_safe_redirect(admin_url('admin.php?page=wpnexus-ai-targets'));
        exit;
    }

    public function handle_delete(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
        $id = (int) ($_GET['id'] ?? 0);
        check_admin_referer('wpnexus_ai_delete_target_' . $id);

        if ($id > 0) {
            $this->repo->delete($id);
        }

        wp_safe_redirect(admin_url('admin.php?page=wpnexus-ai-targets'));
        exit;
    }

    public function handle_test(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
        $id = (int) ($_GET['id'] ?? 0);
        check_admin_referer('wpnexus_ai_test_target_' . $id);

        $row = $this->repo->get($id);
        if (!$row) {
            wp_safe_redirect(admin_url('admin.php?page=wpnexus-ai-targets'));
            exit;
        }

        $client = new BridgeClient();
        $res = $client->ping((string) $row['base_url'], $this->repo->token_plain($row));

        $q = $res['ok'] ? 'ok=1' : ('ok=0&code=' . (int) $res['code']);
        wp_safe_redirect(admin_url('admin.php?page=wpnexus-ai-targets&' . $q));
        exit;
    }
}
