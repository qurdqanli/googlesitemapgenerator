<?php
namespace WPNexusAI\Admin\Screens;

use WPNexusAI\Queue\Dispatcher;
use WPNexusAI\Rules\RulesRepo;
use WPNexusAI\Targets\TargetRepo;

if (!defined('ABSPATH')) { exit; }

final class BulkScreen implements ScreenInterface {

    public function __construct() {
        add_action('admin_post_wpnexus_ai_bulk_enqueue', [$this, 'handle_enqueue']);
    }

    public function render(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }

        $rules = new RulesRepo();
        $targets = new TargetRepo();

        $all_rules = $rules->all(false);
        $all_targets = $targets->all(false);

        echo '<div class="wrap"><h1>Bulk Sync</h1>';
        echo '<p>Enqueue upsert jobs for many posts (useful for initial migration).</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="wpnexus_ai_bulk_enqueue" />';
        wp_nonce_field('wpnexus_ai_bulk_enqueue');

        echo '<table class="form-table"><tbody>';

        echo '<tr><th><label>Post Type</label></th><td><input class="regular-text" name="post_type" value="post" placeholder="post / product"></td></tr>';
        echo '<tr><th><label>Status</label></th><td><input class="regular-text" name="status" value="publish" placeholder="publish"></td></tr>';
        echo '<tr><th><label>Limit</label></th><td><input class="small-text" type="number" name="limit" value="50" min="1" max="500"></td></tr>';

        echo '<tr><th><label>Rule</label></th><td><select name="rule_id">';
        echo '<option value="0">-- choose --</option>';
        foreach ($all_rules as $r) {
            echo '<option value="' . esc_attr((string) $r['id']) . '">' . esc_html((string) $r['name']) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th><label>Target</label></th><td><select name="target_id">';
        echo '<option value="0">All targets in rule</option>';
        foreach ($all_targets as $t) {
            echo '<option value="' . esc_attr((string) $t['id']) . '">' . esc_html((string) $t['name']) . '</option>';
        }
        echo '</select></td></tr>';

        echo '</tbody></table>';

        submit_button('Enqueue Bulk Jobs');

        echo '</form>';

        if (isset($_GET['enqueued'])) {
            echo '<div class="notice notice-success"><p>Enqueued: ' . esc_html((string) $_GET['enqueued']) . '</p></div>';
        }

        echo '</div>';
    }

    public function handle_enqueue(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
        check_admin_referer('wpnexus_ai_bulk_enqueue');

        $post_type = sanitize_text_field((string) ($_POST['post_type'] ?? 'post'));
        $status = sanitize_text_field((string) ($_POST['status'] ?? 'publish'));
        $limit = (int) ($_POST['limit'] ?? 50);
        $limit = max(1, min(500, $limit));

        $rule_id = (int) ($_POST['rule_id'] ?? 0);
        if ($rule_id <= 0) {
            wp_safe_redirect(admin_url('admin.php?page=wpnexus-ai-bulk&enqueued=0'));
            exit;
        }

        $target_id = (int) ($_POST['target_id'] ?? 0);

        $rules = new RulesRepo();
        $rule = $rules->get($rule_id);
        if (!$rule) {
            wp_safe_redirect(admin_url('admin.php?page=wpnexus-ai-bulk&enqueued=0'));
            exit;
        }

        $target_ids = $target_id > 0 ? [$target_id] : $rules->target_ids($rule);

        $q = new \WP_Query([
            'post_type' => $post_type,
            'post_status' => $status,
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        $dispatcher = new Dispatcher();
        $count = 0;

        foreach ((array) $q->posts as $pid) {
            foreach ($target_ids as $tid) {
                $dispatcher->enqueue('upsert', [
                    'post_id' => (int) $pid,
                    'rule_id' => $rule_id,
                    'target_id' => (int) $tid,
                    'event' => 'bulk',
                ], 5);
                $count++;
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=wpnexus-ai-bulk&enqueued=' . $count));
        exit;
    }
}
