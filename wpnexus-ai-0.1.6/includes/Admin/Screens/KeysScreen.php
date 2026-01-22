<?php
namespace WPNexusAI\Admin\Screens;

use WPNexusAI\Admin\Admin;
use WPNexusAI\DB\Repos\KeysRepo;

if (!defined('ABSPATH')) {
	exit;
}

final class KeysScreen extends Screen {

	/** @var KeysRepo */
	private $repo;

	public function __construct() {
		parent::__construct();
		$this->repo = new KeysRepo();
	}

	public function render(): void {
		$this->logger->debug('admin.keys.render');

		$this->render_notices();

		echo '<div class="wpnx-grid">';

		$this->render_manage_card();
		$this->render_help_card();

		echo '</div>';
	}

	private function render_notices(): void {
		$msg = isset($_GET['msg']) ? sanitize_key((string) $_GET['msg']) : '';
		if ($msg === '') {
			return;
		}

		$map = [
			'key_created' => ['success', t('keys_notice_created')],
			'key_create_failed' => ['error', t('keys_notice_create_failed')],
			'key_updated' => ['success', t('keys_notice_updated')],
			'key_update_failed' => ['error', t('keys_notice_update_failed')],
			'key_deleted' => ['success', t('keys_notice_deleted')],
			'key_delete_failed' => ['error', t('keys_notice_delete_failed')],
			'key_toggled' => ['success', t('keys_notice_toggled')],
			'key_toggle_failed' => ['error', t('keys_notice_toggle_failed')],
			'key_imported' => ['success', t('keys_notice_imported')],
			'key_import_failed' => ['error', t('keys_notice_import_failed')],
			'key_duplicate' => ['error', t('keys_notice_duplicate')],
		];

		if (!isset($map[$msg])) {
			return;
		}

		[$type, $text] = $map[$msg];
		$class = $type === 'success' ? 'notice notice-success' : 'notice notice-error';

		if ($msg === 'key_imported') {
        $added = isset($_GET['added']) ? (int) $_GET['added'] : 0;
        $skipped = isset($_GET['skipped']) ? (int) $_GET['skipped'] : 0;
        $text = t('keys_notice_imported_with_counts', [$added, $skipped]);
        }


		echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($text) . '</p></div>';
	}

	private function render_manage_card(): void {
		$rows = $this->repo->list(300);

		$this->card_open(t('keys_title'));
		echo '<p class="wpnx-muted">' . esc_html(t('keys_intro')) . '</p>';

		// Add single key
		echo '<h3>' . esc_html(t('keys_add_title')) . '</h3>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('wpnexus_ai_key_create');
		echo '<input type="hidden" name="action" value="wpnexus_ai_key_create" />';

		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="provider">' . esc_html(t('keys_field_provider')) . '</label></th><td>';
		echo '<select name="provider" id="provider">';
		foreach ($this->providers() as $p) {
			echo '<option value="' . esc_attr($p) . '">' . esc_html($p) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html(t('keys_field_provider_help')) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="key">' . esc_html(t('keys_field_key')) . '</label></th><td>';
		echo '<input name="key" id="key" type="password" class="regular-text" autocomplete="new-password" />';
		echo '<p class="description">' . esc_html(t('keys_field_key_help')) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html(t('keys_field_active')) . '</th><td>';
		echo '<label><input type="checkbox" name="is_active" value="1" checked /> ' . esc_html(t('keys_field_active_label')) . '</label>';
		echo '</td></tr>';

		echo '</tbody></table>';

		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html(t('keys_btn_add')) . '</button></p>';
		echo '</form>';

		// Bulk import
		echo '<hr />';
		echo '<h3>' . esc_html(t('keys_import_title')) . '</h3>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('wpnexus_ai_key_bulk_import');
		echo '<input type="hidden" name="action" value="wpnexus_ai_key_bulk_import" />';

		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="provider_import">' . esc_html(t('keys_field_provider')) . '</label></th><td>';
		echo '<select name="provider" id="provider_import">';
		foreach ($this->providers() as $p) {
			echo '<option value="' . esc_attr($p) . '">' . esc_html($p) . '</option>';
		}
		echo '</select>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="keys">' . esc_html(t('keys_field_import_keys')) . '</label></th><td>';
		echo '<textarea name="keys" id="keys" rows="6" class="large-text code" placeholder="one_key_per_line"></textarea>';
		echo '<p class="description">' . esc_html(t('keys_field_import_help')) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html(t('keys_field_active')) . '</th><td>';
		echo '<label><input type="checkbox" name="is_active" value="1" checked /> ' . esc_html(t('keys_field_active_label')) . '</label>';
		echo '</td></tr>';

		echo '</tbody></table>';

		echo '<p class="submit"><button type="submit" class="button">' . esc_html(t('keys_btn_import')) . '</button></p>';
		echo '</form>';

		// List
		echo '<hr />';
		echo '<h3>' . esc_html(t('keys_list_title')) . '</h3>';

		if (empty($rows)) {
			echo '<p>' . esc_html(t('keys_empty')) . '</p>';
			$this->card_close();
			return;
		}

		echo '<table class="widefat striped" style="margin-top:12px">';
		echo '<thead><tr>';
		echo '<th>' . esc_html(t('keys_col_id')) . '</th>';
		echo '<th>' . esc_html(t('keys_col_provider')) . '</th>';
		echo '<th>' . esc_html(t('keys_col_active')) . '</th>';
		echo '<th>' . esc_html(t('keys_col_cooldown')) . '</th>';
		echo '<th>' . esc_html(t('keys_col_last_used')) . '</th>';
		echo '<th>' . esc_html(t('keys_col_success')) . '</th>';
		echo '<th>' . esc_html(t('keys_col_fail')) . '</th>';
		echo '<th>' . esc_html(t('keys_col_429')) . '</th>';
		echo '<th>' . esc_html(t('keys_col_actions')) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($rows as $r) {
			$id = (int) $r['id'];
			$is_active = ((int) ($r['is_active'] ?? 0) === 1);

			echo '<tr>';
			echo '<td>' . esc_html((string) $id) . '</td>';
			echo '<td>' . esc_html((string) $r['provider']) . '</td>';
			echo '<td>' . esc_html($is_active ? t('yes') : t('no')) . '</td>';
			echo '<td>' . esc_html((string) ($r['cooldown_until'] ?? '-')) . '</td>';
			echo '<td>' . esc_html((string) ($r['last_used_at'] ?? '-')) . '</td>';
			echo '<td>' . esc_html((string) ($r['success_count'] ?? 0)) . '</td>';
			echo '<td>' . esc_html((string) ($r['fail_count'] ?? 0)) . '</td>';
			echo '<td>' . esc_html((string) ($r['rate_429_count'] ?? 0)) . '</td>';

			echo '<td>';

			// Toggle
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:6px">';
			wp_nonce_field('wpnexus_ai_key_toggle');
			echo '<input type="hidden" name="action" value="wpnexus_ai_key_toggle" />';
			echo '<input type="hidden" name="id" value="' . esc_attr((string) $id) . '" />';
			echo '<input type="hidden" name="active" value="' . esc_attr($is_active ? '0' : '1') . '" />';
			echo '<button class="button button-small">' . esc_html($is_active ? t('keys_btn_disable') : t('keys_btn_enable')) . '</button>';
			echo '</form>';

			// Update key (inline)
			echo '<details style="display:inline-block;margin-right:6px"><summary class="button button-small" style="display:inline-block;cursor:pointer;">' . esc_html(t('keys_btn_update')) . '</summary>';
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:8px">';
			wp_nonce_field('wpnexus_ai_key_update');
			echo '<input type="hidden" name="action" value="wpnexus_ai_key_update" />';
			echo '<input type="hidden" name="id" value="' . esc_attr((string) $id) . '" />';
			echo '<input name="key" type="password" class="regular-text" autocomplete="new-password" placeholder="' . esc_attr(t('keys_update_placeholder')) . '" /> ';
			echo '<button class="button">' . esc_html(t('keys_btn_save_update')) . '</button>';
			echo '</form></details>';

			// Delete
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block" onsubmit="return confirm(\'' . esc_js(t('keys_confirm_delete')) . '\');">';
			wp_nonce_field('wpnexus_ai_key_delete');
			echo '<input type="hidden" name="action" value="wpnexus_ai_key_delete" />';
			echo '<input type="hidden" name="id" value="' . esc_attr((string) $id) . '" />';
			echo '<button class="button button-small button-link-delete">' . esc_html(t('keys_btn_delete')) . '</button>';
			echo '</form>';

			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		$this->card_close();
	}

	private function render_help_card(): void {
		$this->card_open(t('keys_help_title'));

		echo '<p class="wpnx-muted">' . esc_html(t('keys_help_body')) . '</p>';

		echo '<h4>' . esc_html(t('keys_help_rotation_title')) . '</h4>';
		echo '<p class="wpnx-muted">' . esc_html(t('keys_help_rotation_body')) . '</p>';

		echo '<h4>' . esc_html(t('keys_help_429_title')) . '</h4>';
		echo '<p class="wpnx-muted">' . esc_html(t('keys_help_429_body')) . '</p>';
		echo '<ul class="wpnx-muted" style="list-style:disc;margin-left:18px">';
		echo '<li>' . esc_html(t('keys_help_429_point_1')) . '</li>';
		echo '<li>' . esc_html(t('keys_help_429_point_2')) . '</li>';
		echo '</ul>';

		$this->card_close();
	}

	/**
	 * @return array<int,string>
	 */
	private function providers(): array {
		return [
			'openai',
			'claude',
			'gemini',
			'custom',
		];
	}
}

