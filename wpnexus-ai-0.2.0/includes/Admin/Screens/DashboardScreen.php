<?php
namespace WPNexusAI\Admin\Screens;

use WPNexusAI\Targets\TargetRepo;
use WPNexusAI\Rules\RulesRepo;
use WPNexusAI\Queue\JobsRepo;

if (!defined('ABSPATH')) { exit; }

final class DashboardScreen implements ScreenInterface {

    public function render(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }

        $targets = new TargetRepo();
        $rules = new RulesRepo();
        $jobs = new JobsRepo();

        $targets_count = count($targets->all(false));
        $rules_enabled = count($rules->all(true));

        $recent = $jobs->recent(10);

        echo '<div class="wrap">';
        echo '<h1>WPNexus AI</h1>';

        echo '<div style="display:flex; gap:16px; flex-wrap:wrap;">';
        echo '<div class="card"><h3>Targets</h3><p style="font-size:24px;">' . esc_html((string) $targets_count) . '</p></div>';
        echo '<div class="card"><h3>Enabled Rules</h3><p style="font-size:24px;">' . esc_html((string) $rules_enabled) . '</p></div>';
        echo '</div>';

        echo '<h2>Recent Jobs</h2>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Type</th><th>Status</th><th>Attempts</th><th>Updated</th><th>Error</th></tr></thead><tbody>';
        foreach ($recent as $r) {
            echo '<tr>';
            echo '<td>' . esc_html((string) $r['id']) . '</td>';
            echo '<td>' . esc_html((string) $r['type']) . '</td>';
            echo '<td>' . esc_html((string) $r['status']) . '</td>';
            echo '<td>' . esc_html((string) $r['attempts']) . '</td>';
            echo '<td>' . esc_html((string) $r['updated_at']) . '</td>';
            echo '<td style="max-width:420px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' . esc_html((string) ($r['last_error'] ?? '')) . '</td>';
            echo '</tr>';
        }
        if (!$recent) {
            echo '<tr><td colspan="6">No jobs yet.</td></tr>';
        }
        echo '</tbody></table>';

        echo '<p style="margin-top:16px;">Tip: Create a <strong>Rule</strong> to auto-enqueue translation & sync jobs when you publish or update content.</p>';
        echo '</div>';
    }
}
