<?php
namespace WPNexusAI\Admin\Screens;

use WPNexusAI\DB\DB;

if (!defined('ABSPATH')) {
	exit;
}

final class JobsScreen extends Screen {

	public function render(): void {
		$this->logger->debug('admin.jobs.render');

		global $wpdb;
		$table = DB::table('jobs');

		// Read-only list. Dispatcher/Action Scheduler T07-də.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results("SELECT id, type, status, attempts, next_run_at, updated_at FROM {$table} ORDER BY id DESC LIMIT 50", ARRAY_A);

		$this->card_open(t('jobs_title'));
		echo '<p class="wpnx-muted">' . esc_html(t('jobs_intro')) . '</p>';

		if (empty($rows)) {
			echo '<p>' . esc_html(t('jobs_empty')) . '</p>';
		} else {
			echo '<table class="widefat striped" style="margin-top:12px">';
			echo '<thead><tr>';
			echo '<th>' . esc_html(t('jobs_col_id')) . '</th>';
			echo '<th>' . esc_html(t('jobs_col_type')) . '</th>';
			echo '<th>' . esc_html(t('jobs_col_status')) . '</th>';
			echo '<th>' . esc_html(t('jobs_col_attempts')) . '</th>';
			echo '<th>' . esc_html(t('jobs_col_next_run')) . '</th>';
			echo '<th>' . esc_html(t('jobs_col_updated')) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ($rows as $r) {
				echo '<tr>';
				echo '<td>' . esc_html((string) $r['id']) . '</td>';
				echo '<td>' . esc_html((string) $r['type']) . '</td>';
				echo '<td>' . esc_html((string) $r['status']) . '</td>';
				echo '<td>' . esc_html((string) $r['attempts']) . '</td>';
				echo '<td>' . esc_html((string) ($r['next_run_at'] ?? '-')) . '</td>';
				echo '<td>' . esc_html((string) ($r['updated_at'] ?? '-')) . '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}

		echo '<hr />';
		echo '<h3>' . esc_html(t('jobs_help_queue_title')) . '</h3>';
		echo '<p class="wpnx-muted">' . esc_html(t('jobs_help_queue_body')) . '</p>';

		$this->card_close();
	}
}
