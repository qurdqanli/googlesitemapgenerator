<?php
namespace WPNexusAI\Admin\Screens;

use WPNexusAI\Licensing\LicensingService;
use WPNexusAI\DB\Repos\LicenseRepo;

if (!defined('ABSPATH')) {
	exit;
}

final class SettingsScreen extends Screen {

	private const NONCE_ACTION = 'wpnexus_ai_settings_save';
	private const NONCE_NAME   = '_wpnexus_ai_settings_nonce';

	public function render(): void {
		$this->logger->debug('admin.settings.render');

		// Handle POST
		$this->maybe_handle_post();

		$lic   = new LicensingService();
		$repo  = new LicenseRepo();
		$row   = $repo->get_row();

		$opt_in = !empty($row['opt_in']);
		$purchase_code = !empty($row['purchase_code']) ? (string) $row['purchase_code'] : '';
		$last_check_at = !empty($row['last_check_at']) ? (string) $row['last_check_at'] : '';
		$grace_until   = !empty($row['grace_until']) ? (string) $row['grace_until'] : '';

				$gemini_model = get_option('wpnexus_ai_gemini_model');
		$gemini_model = is_string($gemini_model) ? $gemini_model : '';
		$gemini_base  = get_option('wpnexus_ai_gemini_base');
		$gemini_base  = is_string($gemini_base) ? $gemini_base : '';

		$ent = $lic->entitlements();
		$limit = (int) $ent->targets_limit;
		$enforcement = $lic->enforcement_enabled();

		$this->card_open(t('settings_title'));
		echo '<p class="wpnx-muted">' . esc_html(t('settings_intro')) . '</p>';

		// Privacy
		echo '<h3>' . esc_html(t('settings_privacy_title')) . '</h3>';
		echo '<p class="wpnx-muted">' . esc_html(t('settings_privacy_body')) . '</p>';

		// Providers
		echo '<h3>' . esc_html(t('settings_providers_title')) . '</h3>';
		echo '<p class="wpnx-muted">' . esc_html(t('settings_providers_body')) . '</p>';

		// Licensing
		echo '<h3>' . esc_html(t('settings_licensing_title')) . '</h3>';
		echo '<p class="wpnx-muted">' . esc_html(t('settings_licensing_body')) . '</p>';

		// Status pills
		echo '<div class="wpnx-kv">';
		echo '<strong>' . esc_html(t('settings_licensing_status')) . '</strong>';

		if ($opt_in) {
			echo '<span>' . $this->pill(t('settings_licensing_status_enabled')) . '</span>';
		} else {
			echo '<span>' . $this->pill(t('settings_licensing_status_disabled')) . '</span>';
		}

		if (!$enforcement) {
			echo '<span style="margin-left:8px">' . $this->pill(t('settings_licensing_status_testing')) . '</span>';
		}

		echo '</div>';

		if (!$enforcement) {
			echo '<p class="wpnx-muted" style="margin-top:10px;">' . esc_html(t('settings_licensing_note_testing')) . '</p>';
		}

		// Form
		echo '<form method="post" action="">';
		wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

		echo '<table class="form-table" role="presentation"><tbody>';

		// Gemini model
		echo '<tr>';
		echo '<th scope="row">' . esc_html(t('settings_gemini_model_label')) . '</th>';
		echo '<td>';
		echo '<input type="text" class="regular-text" name="wpnexus_ai[gemini_model]" value="' . esc_attr($gemini_model) . '" placeholder="gemini-1.5-flash-latest" />';
		echo '<p class="description">' . esc_html(t('settings_gemini_model_help')) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Gemini base URL
		echo '<tr>';
		echo '<th scope="row">' . esc_html(t('settings_gemini_base_label')) . '</th>';
		echo '<td>';
		echo '<input type="url" class="regular-text" name="wpnexus_ai[gemini_base]" value="' . esc_attr($gemini_base) . '" placeholder="https://generativelanguage.googleapis.com/v1/models/" />';
		echo '<p class="description">' . esc_html(t('settings_gemini_base_help')) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Opt-in checkbox
		echo '<tr>';
		echo '<th scope="row">' . esc_html(t('settings_licensing_opt_in')) . '</th>';
		echo '<td>';
		echo '<label>';
		echo '<input type="checkbox" name="wpnexus_ai[opt_in]" value="1" ' . checked($opt_in, true, false) . ' /> ';
		echo esc_html(t('settings_licensing_opt_in_label'));
		echo '</label>';
		echo '<p class="description">' . esc_html(t('settings_licensing_opt_in_help')) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Purchase code
		echo '<tr>';
		echo '<th scope="row">' . esc_html(t('settings_licensing_purchase_code')) . '</th>';
		echo '<td>';
		echo '<input type="text" class="regular-text" name="wpnexus_ai[purchase_code]" value="' . esc_attr($purchase_code) . '" placeholder="XXXX-XXXX-XXXX-XXXX" />';
		echo '<p class="description">' . esc_html(t('settings_licensing_purchase_code_help')) . '</p>';
		echo '</td>';
		echo '</tr>';

		// What we send
		echo '<tr>';
		echo '<th scope="row">' . esc_html(t('settings_licensing_what_we_send')) . '</th>';
		echo '<td>';
		echo '<p class="wpnx-muted" style="margin:0;">' . esc_html(t('settings_licensing_what_we_send_list')) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Entitlements preview
		echo '<tr>';
		echo '<th scope="row">' . esc_html(t('settings_licensing_entitlements')) . '</th>';
		echo '<td>';

		echo '<div class="wpnx-kv">';
		echo '<strong>' . esc_html(t('settings_licensing_targets_limit')) . '</strong> ';
		echo '<span>' . esc_html($limit <= 0 ? t('settings_licensing_targets_unlimited') : (string) $limit) . '</span>';
		echo '</div>';

		$features = is_array($ent->features) ? $ent->features : [];
		if (!empty($features)) {
			echo '<div style="margin-top:6px;">';
			echo '<strong>' . esc_html(t('settings_licensing_features')) . '</strong>';
			echo '<ul style="margin:6px 0 0 18px;">';
			foreach ($features as $k => $v) {
				$k = sanitize_key((string) $k);
				$on = !empty($v);
				echo '<li>' . esc_html($k) . ': ' . esc_html($on ? t('settings_yes') : t('settings_no')) . '</li>';
			}
			echo '</ul>';
			echo '</div>';
		}

		if ($last_check_at !== '' || $grace_until !== '') {
			echo '<div style="margin-top:8px;">';
			if ($last_check_at !== '') {
				echo '<div class="wpnx-kv"><strong>' . esc_html(t('settings_licensing_last_check')) . '</strong><span>' . esc_html($last_check_at) . '</span></div>';
			}
			if ($grace_until !== '') {
				echo '<div class="wpnx-kv"><strong>' . esc_html(t('settings_licensing_grace_until')) . '</strong><span>' . esc_html($grace_until) . '</span></div>';
			}
			echo '</div>';
		}

		echo '</td>';
		echo '</tr>';

		echo '</tbody></table>';

		submit_button(t('settings_save'), 'primary', 'wpnexus_ai_save', true);

		echo '</form>';

		$this->card_close();
	}

	private function maybe_handle_post(): void {
		if (!is_admin()) {
			return;
		}

		if (!current_user_can('manage_options')) {
			return;
		}

		if (empty($_POST)) {
			return;
		}

		$nonce = isset($_POST[self::NONCE_NAME]) ? (string) $_POST[self::NONCE_NAME] : '';
		if ($nonce === '' || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
			return;
		}

		$data = isset($_POST['wpnexus_ai']) && is_array($_POST['wpnexus_ai']) ? (array) $_POST['wpnexus_ai'] : [];

		$opt_in = !empty($data['opt_in']) ? 1 : 0;

		$purchase_code = isset($data['purchase_code']) ? sanitize_text_field((string) $data['purchase_code']) : '';
		if (strlen($purchase_code) > 200) {
			$purchase_code = substr($purchase_code, 0, 200);
		}

		
		$gemini_model = isset($data['gemini_model']) ? sanitize_text_field((string) $data['gemini_model']) : null;
		if ($gemini_model !== null) {
			$gemini_model = trim($gemini_model);
			if (strlen($gemini_model) > 120) {
				$gemini_model = substr($gemini_model, 0, 120);
			}
			update_option('wpnexus_ai_gemini_model', $gemini_model, false);
		}

		$gemini_base_raw = isset($data['gemini_base']) ? (string) $data['gemini_base'] : null;
		if ($gemini_base_raw !== null) {
			$gemini_base_raw = trim($gemini_base_raw);
			$gemini_base = $gemini_base_raw === '' ? '' : esc_url_raw($gemini_base_raw);
			if (strlen($gemini_base) > 300) {
				$gemini_base = substr($gemini_base, 0, 300);
			}
			update_option('wpnexus_ai_gemini_base', $gemini_base, false);
		}
$this->logger->info('admin.settings.license.save.start', [
			'opt_in' => $opt_in,
			'has_purchase_code' => $purchase_code !== '' ? 1 : 0,
		]);

		$repo = new LicenseRepo();
		$current = $repo->get_row();

		// Keep entitlements_json untouched for now; later (T19) we’ll fetch from emblem.az if opt-in enabled.
		$ent_json = !empty($current['entitlements_json']) ? (string) $current['entitlements_json'] : '{}';

		$ok = $repo->upsert([
			'opt_in'            => $opt_in,
			'purchase_code'     => $purchase_code,
			'entitlements_json' => $ent_json,
			// last_check_at/grace_until kept as-is
			'last_check_at'     => !empty($current['last_check_at']) ? (string) $current['last_check_at'] : null,
			'grace_until'       => !empty($current['grace_until']) ? (string) $current['grace_until'] : null,
		]);

		$this->logger->info('admin.settings.license.save.done', [
			'ok' => $ok ? 1 : 0,
		]);

		add_action('admin_notices', function () use ($ok) {
			if ($ok) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(t('settings_saved')) . '</p></div>';
			} else {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(t('settings_save_failed')) . '</p></div>';
			}
		});
	}
}
