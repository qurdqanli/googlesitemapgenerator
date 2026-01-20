<?php
namespace WPNexusAIBridge\Admin;

use WPNexusAIBridge\Logging\Logger;
use WPNexusAIBridge\Security\TokenManager;

if (!defined('ABSPATH')) {
	exit;
}

final class Admin {

	public const CAP = 'manage_options';

	private $logger;

	public function __construct() {
		$this->logger = Logger::instance();
	}

	public function init(): void {
		add_action('admin_menu', [$this, 'menu']);
		add_action('admin_post_wpnexus_ai_bridge_regen_token', [$this, 'regen_token']);
		add_action('admin_post_wpnexus_ai_bridge_hide_token', [$this, 'hide_token']);
	}

	public function menu(): void {
		add_options_page(
			t('bridge_admin_title'),
			t('bridge_admin_menu'),
			self::CAP,
			'wpnexus-ai-bridge',
			[$this, 'render']
		);
	}

	public function render(): void {
		if (!current_user_can(self::CAP)) {
			wp_die(esc_html__('Sorry, you are not allowed to access this page.'));
		}

		TokenManager::ensure_exists();

		$this->logger->info('bridge.admin.render');

		$msg = isset($_GET['msg']) ? sanitize_key((string) $_GET['msg']) : '';
		if ($msg === 'token_regenerated') {
			echo '<div class="notice notice-success"><p>' . esc_html(t('bridge_admin_notice_regenerated')) . '</p></div>';
		} elseif ($msg === 'token_hidden') {
			echo '<div class="notice notice-success"><p>' . esc_html(t('bridge_admin_notice_hidden')) . '</p></div>';
		}

		$plain = TokenManager::get_last_plain();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html(t('bridge_admin_title')) . '</h1>';
		echo '<p>' . esc_html(t('bridge_admin_intro')) . '</p>';

		echo '<h2>' . esc_html(t('bridge_admin_token_title')) . '</h2>';

		if ($plain) {
			echo '<p><strong>' . esc_html(t('bridge_admin_token_visible')) . '</strong></p>';
			echo '<pre style="padding:12px;background:#fff;border:1px solid #dcdcde;border-radius:8px;max-width:900px;white-space:pre-wrap;">' . esc_html($plain) . '</pre>';
			echo '<p style="color:#646970">' . esc_html(t('bridge_admin_token_hint_copy')) . '</p>';

			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:10px">';
			wp_nonce_field('wpnexus_ai_bridge_hide_token');
			echo '<input type="hidden" name="action" value="wpnexus_ai_bridge_hide_token" />';
			echo '<button class="button">' . esc_html(t('bridge_admin_btn_hide')) . '</button>';
			echo '</form>';
		} else {
			echo '<p style="color:#646970">' . esc_html(t('bridge_admin_token_hidden')) . '</p>';
		}

		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('wpnexus_ai_bridge_regen_token');
		echo '<input type="hidden" name="action" value="wpnexus_ai_bridge_regen_token" />';
		echo '<button class="button button-primary">' . esc_html(t('bridge_admin_btn_regen')) . '</button>';
		echo '</form>';

		echo '<hr />';
		echo '<h2>' . esc_html(t('bridge_admin_app_password_title')) . '</h2>';
		echo '<p style="color:#646970">' . esc_html(t('bridge_admin_app_password_body')) . '</p>';

		echo '</div>';
	}

	public function regen_token(): void {
		if (!current_user_can(self::CAP)) {
			wp_die(esc_html__('Sorry, you are not allowed to access this page.'));
		}
		check_admin_referer('wpnexus_ai_bridge_regen_token');

		$this->logger->info('bridge.admin.token.regen');

		TokenManager::regenerate();
		TokenManager::ensure_token_user();

		wp_safe_redirect(add_query_arg(['page' => 'wpnexus-ai-bridge', 'msg' => 'token_regenerated'], admin_url('options-general.php')));
		exit;
	}

	public function hide_token(): void {
		if (!current_user_can(self::CAP)) {
			wp_die(esc_html__('Sorry, you are not allowed to access this page.'));
		}
		check_admin_referer('wpnexus_ai_bridge_hide_token');

		$this->logger->info('bridge.admin.token.hide');

		TokenManager::hide_last_plain();

		wp_safe_redirect(add_query_arg(['page' => 'wpnexus-ai-bridge', 'msg' => 'token_hidden'], admin_url('options-general.php')));
		exit;
	}
}
