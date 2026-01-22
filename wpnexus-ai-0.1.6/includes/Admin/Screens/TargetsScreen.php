<?php
namespace WPNexusAI\Admin\Screens;

use WPNexusAI\Admin\Admin;
use WPNexusAI\DB\Repos\TargetsRepo;

if (!defined('ABSPATH')) {
	exit;
}

final class TargetsScreen extends Screen {

	/** @var TargetsRepo */
	private $repo;

	public function __construct() {
		parent::__construct();
		$this->repo = new TargetsRepo();
	}

	public function render(): void {
		$this->logger->debug('admin.targets.render');

		$action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : '';
		$id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

		$this->render_notices();

		if ($action === 'add') {
			$this->render_form(null);
			return;
		}

		if ($action === 'edit' && $id > 0) {
			$target = $this->repo->get($id);
			if (!$target) {
				echo '<div class="notice notice-error"><p>' . esc_html(t('targets_not_found')) . '</p></div>';
				$this->render_list();
				return;
			}
			$this->render_form($target);
			return;
		}

		$this->render_list();
	}

	private function render_notices(): void {
		$msg = isset($_GET['msg']) ? sanitize_key((string) $_GET['msg']) : '';
		if ($msg === '') {
			return;
		}

		$map = [
			'target_saved'       => ['success', t('targets_notice_saved')],
			'target_deleted'     => ['success', t('targets_notice_deleted')],
			'target_save_failed' => ['error',   t('targets_notice_save_failed')],
			'target_test_ok'     => ['success', t('targets_notice_test_ok')],
			'target_test_failed' => ['error',   t('targets_notice_test_failed')],
		];

		if (!isset($map[$msg])) {
			return;
		}

		[$type, $text] = $map[$msg];
		$class = $type === 'success' ? 'notice notice-success' : 'notice notice-error';

		echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($text) . '</p></div>';
	}

	private function render_list(): void {
		$rows = $this->repo->list(200);

		$add_url = Admin::url('wpnexus-ai-targets', ['action' => 'add']);

		echo '<div class="wpnx-grid">';

		$this->card_open(t('targets_title'));
		echo '<p class="wpnx-muted">' . esc_html(t('targets_intro')) . '</p>';

		echo '<div class="wpnx-actions">';
		echo '<a class="button button-primary" href="' . esc_url($add_url) . '">' . esc_html(t('targets_add_btn')) . '</a>';
		echo '</div>';

		echo '<hr />';

		if (empty($rows)) {
			echo '<p>' . esc_html(t('targets_empty')) . '</p>';
		} else {
			echo '<table class="widefat striped" style="margin-top:12px">';
			echo '<thead><tr>';
			echo '<th>' . esc_html(t('targets_col_id')) . '</th>';
			echo '<th>' . esc_html(t('targets_col_base_url')) . '</th>';
			echo '<th>' . esc_html(t('targets_col_auth')) . '</th>';
			echo '<th>' . esc_html(t('targets_col_default_lang')) . '</th>';
			echo '<th>' . esc_html(t('targets_col_status_default')) . '</th>';
			echo '<th>' . esc_html(t('targets_col_updated')) . '</th>';
			echo '<th>' . esc_html(t('targets_col_actions')) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ($rows as $r) {
				$edit_url = Admin::url('wpnexus-ai-targets', ['action' => 'edit', 'id' => (int) $r['id']]);

				echo '<tr>';
				echo '<td>' . esc_html((string) $r['id']) . '</td>';
				echo '<td><code>' . esc_html((string) $r['base_url']) . '</code></td>';
				echo '<td>' . esc_html((string) ($r['auth_method'] ?: t('targets_auth_none'))) . '</td>';
				echo '<td>' . esc_html((string) $r['default_language']) . '</td>';
				echo '<td>' . esc_html((string) $r['status_default']) . '</td>';
				echo '<td>' . esc_html((string) $r['updated_at']) . '</td>';
				echo '<td>';

				echo '<a class="button button-small" href="' . esc_url($edit_url) . '">' . esc_html(t('targets_action_edit')) . '</a> ';

				// Test form
				echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-left:6px">';
				wp_nonce_field('wpnexus_ai_target_test');
				echo '<input type="hidden" name="action" value="wpnexus_ai_target_test" />';
				echo '<input type="hidden" name="id" value="' . esc_attr((string) (int) $r['id']) . '" />';
				echo '<button type="submit" class="button button-small">' . esc_html(t('targets_action_test')) . '</button>';
				echo '</form> ';

				// Delete form
				echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-left:6px" onsubmit="return confirm(\'' . esc_js(t('targets_confirm_delete')) . '\');">';
				wp_nonce_field('wpnexus_ai_target_delete');
				echo '<input type="hidden" name="action" value="wpnexus_ai_target_delete" />';
				echo '<input type="hidden" name="id" value="' . esc_attr((string) (int) $r['id']) . '" />';
				echo '<button type="submit" class="button button-small button-link-delete">' . esc_html(t('targets_action_delete')) . '</button>';
				echo '</form>';

				echo '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}

		$this->card_close();

		// Sidebar: Bridge help
		$this->card_open(t('targets_bridge_title'));
		echo '<p class="wpnx-muted">' . esc_html(t('targets_bridge_body')) . '</p>';
		echo '<ul class="wpnx-muted" style="list-style:disc;margin-left:18px">';
		echo '<li>' . esc_html(t('targets_bridge_point_1')) . '</li>';
		echo '<li>' . esc_html(t('targets_bridge_point_2')) . '</li>';
		echo '</ul>';
		$this->card_close();

		echo '</div>';
	}

	/**
	 * @param array<string,mixed>|null $target
	 */
	private function render_form(?array $target): void {
		$is_edit = is_array($target) && !empty($target['id']);
		$id      = $is_edit ? (int) $target['id'] : 0;

		$back_url = Admin::url('wpnexus-ai-targets');

		$base_url = $is_edit ? (string) ($target['base_url'] ?? '') : '';
		$auth_method = $is_edit ? (string) ($target['auth_method'] ?? '') : '';
		$auth_user   = $is_edit ? (string) ($target['auth_user'] ?? '') : '';

		$network_site_id = $is_edit ? (string) ($target['network_site_id'] ?? '') : '';
		$default_language = $is_edit ? (string) ($target['default_language'] ?? 'auto') : 'auto';
		$fallback_language = $is_edit ? (string) ($target['fallback_language'] ?? 'en') : 'en';

		$seo_canonical_mode = $is_edit ? (string) ($target['seo_canonical_mode'] ?? 'self') : 'self';
		$seo_canonical_custom = $is_edit ? (string) ($target['seo_canonical_custom'] ?? '') : '';

		$status_default = $is_edit ? (string) ($target['status_default'] ?? 'draft') : 'draft';

		echo '<div class="wpnx-grid">';

		$this->card_open($is_edit ? t('targets_edit_title') : t('targets_add_title'));

		echo '<p><a href="' . esc_url($back_url) . '">← ' . esc_html(t('targets_back_to_list')) . '</a></p>';

		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('wpnexus_ai_target_save');
		echo '<input type="hidden" name="action" value="wpnexus_ai_target_save" />';
		echo '<input type="hidden" name="id" value="' . esc_attr((string) $id) . '" />';

		echo '<table class="form-table" role="presentation"><tbody>';

		// Base URL
		echo '<tr>';
		echo '<th scope="row"><label for="base_url">' . esc_html(t('targets_field_base_url')) . '</label></th>';
		echo '<td>';
		echo '<input name="base_url" id="base_url" type="url" class="regular-text" required placeholder="https://example.com" value="' . esc_attr($base_url) . '" />';
		echo '<p class="description">' . esc_html(t('targets_field_base_url_help')) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Network site id
		echo '<tr>';
		echo '<th scope="row"><label for="network_site_id">' . esc_html(t('targets_field_network_site_id')) . '</label></th>';
		echo '<td>';
		echo '<input name="network_site_id" id="network_site_id" type="number" class="small-text" placeholder="(optional)" value="' . esc_attr($network_site_id) . '" />';
		echo '<p class="description">' . esc_html(t('targets_field_network_site_id_help')) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Auth method
		echo '<tr>';
		echo '<th scope="row"><label for="auth_method">' . esc_html(t('targets_field_auth_method')) . '</label></th>';
		echo '<td>';
		echo '<select name="auth_method" id="auth_method">';
		echo '<option value="" ' . selected($auth_method, '', false) . '>' . esc_html(t('targets_auth_none')) . '</option>';
		echo '<option value="bridge_token" ' . selected($auth_method, 'bridge_token', false) . '>' . esc_html(t('targets_auth_bridge_token')) . '</option>';
		echo '<option value="app_password" ' . selected($auth_method, 'app_password', false) . '>' . esc_html(t('targets_auth_app_password')) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html(t('targets_field_auth_method_help')) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Auth user
		echo '<tr>';
		echo '<th scope="row"><label for="auth_user">' . esc_html(t('targets_field_auth_user')) . '</label></th>';
		echo '<td>';
		echo '<input name="auth_user" id="auth_user" type="text" class="regular-text" value="' . esc_attr($auth_user) . '" />';
		echo '<p class="description">' . esc_html(t('targets_field_auth_user_help')) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Bridge token
		echo '<tr>';
		echo '<th scope="row"><label for="bridge_token">' . esc_html(t('targets_field_bridge_token')) . '</label></th>';
		echo '<td>';
		echo '<input name="bridge_token" id="bridge_token" type="password" class="regular-text" value="" autocomplete="new-password" />';
		echo '<p class="description">' . esc_html($is_edit ? t('targets_field_secret_keep_help') : t('targets_field_bridge_token_help')) . '</p>';
		echo '</td>';
		echo '</tr>';

		// App password
		echo '<tr>';
		echo '<th scope="row"><label for="app_password">' . esc_html(t('targets_field_app_password')) . '</label></th>';
		echo '<td>';
		echo '<input name="app_password" id="app_password" type="password" class="regular-text" value="" autocomplete="new-password" />';
		echo '<p class="description">' . esc_html($is_edit ? t('targets_field_secret_keep_help') : t('targets_field_app_password_help')) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Default language
		echo '<tr>';
		echo '<th scope="row"><label for="default_language">' . esc_html(t('targets_field_default_language')) . '</label></th>';
		echo '<td>';
		echo '<input name="default_language" id="default_language" type="text" class="regular-text" placeholder="auto / en / az / ..." value="' . esc_attr($default_language) . '" />';
		echo '<p class="description">' . esc_html(t('targets_field_default_language_help')) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Fallback language
		echo '<tr>';
		echo '<th scope="row"><label for="fallback_language">' . esc_html(t('targets_field_fallback_language')) . '</label></th>';
		echo '<td>';
		echo '<input name="fallback_language" id="fallback_language" type="text" class="regular-text" placeholder="en" value="' . esc_attr($fallback_language) . '" />';
		echo '<p class="description">' . esc_html(t('targets_field_fallback_language_help')) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Status default
		echo '<tr>';
		echo '<th scope="row"><label for="status_default">' . esc_html(t('targets_field_status_default')) . '</label></th>';
		echo '<td>';
		echo '<select name="status_default" id="status_default">';
		foreach (['draft','publish','pending','private'] as $st) {
			echo '<option value="' . esc_attr($st) . '" ' . selected($status_default, $st, false) . '>' . esc_html($st) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html(t('targets_field_status_default_help')) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Canonical mode
		echo '<tr>';
		echo '<th scope="row"><label for="seo_canonical_mode">' . esc_html(t('targets_field_canonical_mode')) . '</label></th>';
		echo '<td>';
		echo '<select name="seo_canonical_mode" id="seo_canonical_mode">';
		echo '<option value="self" ' . selected($seo_canonical_mode, 'self', false) . '>' . esc_html(t('seo_canonical_self')) . '</option>';
		echo '<option value="source" ' . selected($seo_canonical_mode, 'source', false) . '>' . esc_html(t('seo_canonical_source')) . '</option>';
		echo '<option value="custom" ' . selected($seo_canonical_mode, 'custom', false) . '>' . esc_html(t('seo_canonical_custom')) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html(t('targets_field_canonical_mode_help')) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Canonical custom
		echo '<tr>';
		echo '<th scope="row"><label for="seo_canonical_custom">' . esc_html(t('targets_field_canonical_custom')) . '</label></th>';
		echo '<td>';
		echo '<input name="seo_canonical_custom" id="seo_canonical_custom" type="url" class="regular-text" placeholder="https://..." value="' . esc_attr($seo_canonical_custom) . '" />';
		echo '<p class="description">' . esc_html(t('targets_field_canonical_custom_help')) . '</p>';
		echo '</td>';
		echo '</tr>';

		echo '</tbody></table>';

		echo '<p class="submit">';
		echo '<button type="submit" class="button button-primary">' . esc_html(t('targets_btn_save')) . '</button> ';
		echo '<a class="button" href="' . esc_url($back_url) . '">' . esc_html(t('targets_btn_cancel')) . '</a>';
		echo '</p>';

		echo '</form>';

		// Test connection section (only for saved targets)
		echo '<hr />';
		echo '<h3>' . esc_html(t('targets_test_title')) . '</h3>';
		if ($is_edit) {
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
			wp_nonce_field('wpnexus_ai_target_test');
			echo '<input type="hidden" name="action" value="wpnexus_ai_target_test" />';
			echo '<input type="hidden" name="id" value="' . esc_attr((string) $id) . '" />';
			echo '<button type="submit" class="button">' . esc_html(t('targets_btn_test')) . '</button>';
			echo '</form>';

			$this->render_last_test($id);
		} else {
			echo '<p class="wpnx-muted">' . esc_html(t('targets_test_save_first')) . '</p>';
		}

		$this->card_close();

		// Sidebar help
		$this->card_open(t('targets_help_title'));
		echo '<p class="wpnx-muted">' . esc_html(t('targets_help_body')) . '</p>';
		echo '<ul class="wpnx-muted" style="list-style:disc;margin-left:18px">';
		echo '<li>' . esc_html(t('targets_help_point_1')) . '</li>';
		echo '<li>' . esc_html(t('targets_help_point_2')) . '</li>';
		echo '<li>' . esc_html(t('targets_help_point_3')) . '</li>';
		echo '</ul>';
		$this->card_close();

		echo '</div>';
	}

	private function render_last_test(int $id): void {
		$show = isset($_GET['test']) ? (int) $_GET['test'] : 0;
		if ($show !== 1) {
			return;
		}

		$key = 'wpnexus_ai_target_test_' . get_current_user_id() . '_' . $id;
		$result = get_transient($key);
		if (!is_array($result)) {
			return;
		}

		echo '<div style="margin-top:12px">';
		echo '<h4>' . esc_html(t('targets_test_result_title')) . '</h4>';

		$status = isset($result['status']) ? (string) $result['status'] : '-';
		$ok = !empty($result['ok']);
		$bridge = !empty($result['bridge_detected']);

		echo '<div class="wpnx-kv"><strong>' . esc_html(t('targets_test_status')) . '</strong><span class="wpnx-muted">' . esc_html($status) . '</span></div>';
		echo '<div class="wpnx-kv"><strong>' . esc_html(t('targets_test_ok')) . '</strong><span class="wpnx-muted">' . esc_html($ok ? t('yes') : t('no')) . '</span></div>';
		echo '<div class="wpnx-kv"><strong>' . esc_html(t('targets_test_bridge')) . '</strong><span class="wpnx-muted">' . esc_html($bridge ? t('yes') : t('no')) . '</span></div>';

		if (!empty($result['error'])) {
			echo '<p class="wpnx-muted"><strong>' . esc_html(t('targets_test_error')) . ':</strong> ' . esc_html((string) $result['error']) . '</p>';
		}

		if (isset($result['body'])) {
			echo '<details style="margin-top:8px"><summary>' . esc_html(t('targets_test_body')) . '</summary>';
			echo '<pre style="white-space:pre-wrap;margin-top:8px">' . esc_html(wp_json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '</pre>';
			echo '</details>';
		}

		if (!$bridge) {
			echo '<p class="wpnx-muted" style="margin-top:8px">' . esc_html(t('targets_test_bridge_missing_hint')) . '</p>';
		}

		echo '</div>';
	}
}

