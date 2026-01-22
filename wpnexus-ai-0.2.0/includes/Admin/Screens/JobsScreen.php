<?php
namespace WPNexusAI\Admin\Screens;

use WPNexusAI\Queue\JobsRepo;
use WPNexusAI\Queue\JobRunner;

if (!defined('ABSPATH')) { exit; }

final class JobsScreen implements ScreenInterface {

    /** @var JobsRepo */
    private $repo;

    public function __construct() {
        $this->repo = new JobsRepo();

        add_action('admin_post_wpnexus_ai_retry_job', [$this, 'handle_retry']);
        add_action('admin_post_wpnexus_ai_run_sweep', [$this, 'handle_sweep']);
    }

    public function render(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }

        $rows = $this->repo->recent(100);

        echo '<div class="wrap"><h1>Jobs</h1>';

        $sweep = wp_nonce_url(admin_url('admin-post.php?action=wpnexus_ai_run_sweep'), 'wpnexus_ai_run_sweep');
        echo '<p><a class="button button-secondary" href="' . esc_url($sweep) . '">Run Sweep Now</a></p>';

        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Type</th><th>Status</th><th>Attempts</th><th>Updated</th><th>Error</th><th>Actions</th></tr></thead><tbody>';

        foreach ($rows as $r) {
            $retry = wp_nonce_url(admin_url('admin-post.php?action=wpnexus_ai_retry_job&id=' . (int) $r['id']), 'wpnexus_ai_retry_job_' . (int) $r['id']);
            echo '<tr>';
            echo '<td>' . esc_html((string) $r['id']) . '</td>';
            echo '<td>' . esc_html((string) $r['type']) . '</td>';
            echo '<td>' . esc_html((string) $r['status']) . '</td>';
            echo '<td>' . esc_html((string) $r['attempts']) . '</td>';
            echo '<td>' . esc_html((string) $r['updated_at']) . '</td>';
            echo '<td style="max-width:420px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' . esc_html((string) ($r['last_error'] ?? '')) . '</td>';
            echo '<td>';
            if ((string) $r['status'] !== 'done') {
                echo '<a class="button" href="' . esc_url($retry) . '">Retry</a>';
            } else {
                echo '-';
            }
            echo '</td>';
            echo '</tr>';
        }

        if (!$rows) {
            echo '<tr><td colspan="7">No jobs yet.</td></tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    public function handle_retry(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
        $id = (int) ($_GET['id'] ?? 0);
        check_admin_referer('wpnexus_ai_retry_job_' . $id);

        if ($id > 0) {
            $this->repo->reset($id);
            // Schedule sweep quickly.
            if (function_exists('as_schedule_single_action')) {
                as_schedule_single_action(time() + 1, JobRunner::HOOK_SWEEP, [], JobRunner::GROUP);
            } else {
                wp_schedule_single_event(time() + 1, JobRunner::HOOK_SWEEP);
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=wpnexus-ai-jobs'));
        exit;
    }

    public function handle_sweep(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
        check_admin_referer('wpnexus_ai_run_sweep');

        JobRunner::sweep_action();

        wp_safe_redirect(admin_url('admin.php?page=wpnexus-ai-jobs'));
        exit;
    }
}
