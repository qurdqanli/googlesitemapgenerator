<?php
namespace WPNexusAI\Admin\Screens;

use WPNexusAI\Admin\Admin;

if (!defined('ABSPATH')) {
	exit;
}

final class DashboardScreen extends Screen {

	public function render(): void {
		$this->logger->debug('admin.dashboard.render');

		$bridge_url = 'https://wordpress.org/plugins/'; // placeholder (real link packaging-də)
		$targets_url = Admin::url('wpnexus-ai-targets');
		$keys_url = Admin::url('wpnexus-ai-keys');
		$jobs_url = Admin::url('wpnexus-ai-jobs');

		echo '<div class="wpnx-grid">';

		// Main
		$this->card_open(t('dashboard_title'));
		echo '<p class="wpnx-muted">' . esc_html(t('dashboard_intro')) . '</p>';

		echo '<div class="wpnx-actions">';
		echo '<a class="button button-primary" href="' . esc_url($targets_url) . '">' . esc_html(t('dashboard_cta_targets')) . '</a>';
		echo '<a class="button" href="' . esc_url($keys_url) . '">' . esc_html(t('dashboard_cta_keys')) . '</a>';
		echo '<a class="button" href="' . esc_url($jobs_url) . '">' . esc_html(t('dashboard_cta_jobs')) . '</a>';
		echo '</div>';

		echo '<hr />';

		echo '<h3>' . esc_html(t('dashboard_help_bridge_title')) . '</h3>';
		echo '<p class="wpnx-muted">' . esc_html(t('dashboard_help_bridge_body')) . '</p>';
		echo '<ul class="wpnx-muted" style="list-style:disc;margin-left:18px">';
		echo '<li>' . esc_html(t('dashboard_help_bridge_point_1')) . '</li>';
		echo '<li>' . esc_html(t('dashboard_help_bridge_point_2')) . '</li>';
		echo '<li>' . esc_html(t('dashboard_help_bridge_point_3')) . '</li>';
		echo '</ul>';

		echo '<div class="wpnx-actions">';
		echo '<a class="button" target="_blank" rel="noreferrer noopener" href="' . esc_url($bridge_url) . '">' . esc_html(t('dashboard_bridge_install_btn')) . '</a>';
		echo '</div>';

		$this->card_close();

		// Sidebar
		$this->card_open(t('dashboard_status_title'));

		$this->kv(t('dashboard_status_version'), defined('WPNEXUS_AI_VERSION') ? WPNEXUS_AI_VERSION : '-');
		$this->kv(t('dashboard_status_db_version'), (string) (int) get_option('wpnexus_ai_db_version', 0));
		$this->kv(t('dashboard_status_queue'), t('dashboard_status_queue_placeholder'));
		$this->kv(t('dashboard_status_licensing'), t('dashboard_status_licensing_disabled'));

		echo '<p class="wpnx-muted" style="margin-top:12px">' . esc_html(t('dashboard_status_note')) . '</p>';

		$this->card_close();

		echo '</div>';
	}
}
