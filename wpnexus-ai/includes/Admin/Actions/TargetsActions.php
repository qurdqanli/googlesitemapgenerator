<?php
namespace WPNexusAI\Admin\Actions;

use WPNexusAI\Admin\Admin;
use WPNexusAI\DB\Repos\TargetsRepo;
use WPNexusAI\Logging\Logger;
use WPNexusAI\Bridge\Client\BridgeClient;

if (!defined('ABSPATH')) {
	exit;
}

final class TargetsActions {

	/** @var Logger */
	private $logger;

	/** @var TargetsRepo */
	private $repo;

	public function __construct() {
		$this->logger = Logger::instance();
		$this->repo   = new TargetsRepo();
	}

	public function init(): void {
		add_action('admin_post_wpnexus_ai_target_save', [$this, 'save']);
		add_action('admin_post_wpnexus_ai_target_delete', [$this, 'delete']);
		add_action('admin_post_wpnexus_ai_target_test', [$this, 'test']);
	}

	public function save(): void {
		if (!current_user_can(Admin::CAPABILITY)) {
			wp_die(esc_html__('Sorry, you are not allowed to access this page.'));
		}

		check_admin_referer('wpnexus_ai_target_save');

		$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

		$base_url = isset($_POST['base_url']) ? (string) wp_unslash($_POST['base_url']) : '';
		$auth_method = isset($_POST['auth_method']) ? sanitize_key((string) wp_unslash($_POST['auth_method'])) : '';
		$auth_user = isset($_POST['auth_user']) ? sanitize_text_field((string) wp_unslash($_POST['auth_user'])) : '';

		$bridge_token = isset($_POST['bridge_token']) ? trim((string) wp_unslash($_POST['bridge_token'])) : '';
		$app_password = isset($_POST['app_password']) ? trim((string) wp_unslash($_POST['app_password'])) : '';

		$network_site_id = isset($_POST['network_site_id']) ? trim((string) wp_unslash($_POST['network_site_id'])) : '';
		$default_language = isset($_POST['default_language']) ? trim((string) wp_unslash($_POST['default_language'])) : 'auto';
		$fallback_language = isset($_POST['fallback_language']) ? trim((string) wp_unslash($_POST['fallback_language'])) : 'en';

		$seo_canonical_mode = isset($_POST['seo_canonical_mode']) ? sanitize_key((string) wp_unslash($_POST['seo_canonical_mode'])) : 'self';
		$seo_canonical_custom = isset($_POST['seo_canonical_custom']) ? trim((string) wp_unslash($_POST['seo_canonical_custom'])) : '';

		$status_default = isset($_POST['status_default']) ? sanitize_key((string) wp_unslash($_POST['status_default'])) : 'draft';

		$errors = [];

		$base_url_norm = $this->repo->normalize_base_url($base_url);
		$parts = wp_parse_url($base_url_norm);

		if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
			$errors[] = 'invalid_base_url';
		}

		$secrets = null; // null means keep existing secrets on update

		if ($auth_method === 'bridge_token') {
			if ($id <= 0) {
				// Creating: require token
				if ($bridge_token === '') {
					$errors[] = 'missing_bridge_token';
				} else {
					$secrets = ['token' => $bridge_token];
				}
			} else {
				// Updating: token optional (blank => keep)
				if ($bridge_token !== '') {
					$secrets = ['token' => $bridge_token];
				}
			}
		} elseif ($auth_method === 'app_password') {
			if ($auth_user === '') {
				$errors[] = 'missing_auth_user';
			}

			if ($id <= 0) {
				if ($app_password === '') {
					$errors[] = 'missing_app_password';
				} else {
					$secrets = ['app_password' => $app_password];
				}
			} else {
				if ($app_password !== '') {
					$secrets = ['app_password' => $app_password];
				}
			}
		} else {
			// No auth
			$auth_method = '';
			$auth_user = '';
			// If creating without auth, secrets should be empty (clear)
			$secrets = ($id <= 0) ? [] : null; // keep existing if editing unless user changes auth
		}

		if (!empty($errors)) {
			$this->logger->warning('targets.save.validation_failed', [
				'id' => $id,
				'errors' => $errors,
			]);

			$url = Admin::url('wpnexus-ai-targets', [
				'action' => ($id > 0 ? 'edit' : 'add'),
				'id' => ($id > 0 ? $id : null),
				'msg' => 'target_save_failed',
			]);

			wp_safe_redirect($url);
			exit;
		}

		$data = [
			'id' => $id,
			'base_url' => $base_url_norm,
			'auth_method' => $auth_method,
			'auth_user' => $auth_user,
			'network_site_id' => $network_site_id,
			'default_language' => $default_language,
			'fallback_language' => $fallback_language,
			'seo_canonical_mode' => $seo_canonical_mode,
			'seo_canonical_custom' => $seo_canonical_custom,
			'status_default' => $status_default,
			'flags_json' => null,
		];

		$this->logger->info('targets.save.start', [
			'id' => $id,
			'base_url' => $base_url_norm,
			'auth_method' => $auth_method,
		]);

		$new_id = $this->repo->upsert($data, $secrets);

		$this->logger->info('targets.save.done', ['id' => $new_id]);

		$url = Admin::url('wpnexus-ai-targets', [
			'action' => 'edit',
			'id' => $new_id,
			'msg' => 'target_saved',
		]);

		wp_safe_redirect($url);
		exit;
	}

	public function delete(): void {
		if (!current_user_can(Admin::CAPABILITY)) {
			wp_die(esc_html__('Sorry, you are not allowed to access this page.'));
		}

		check_admin_referer('wpnexus_ai_target_delete');

		$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

		$this->logger->info('targets.delete.request', ['id' => $id]);

		if ($id > 0) {
			$this->repo->delete($id);
		}

		$url = Admin::url('wpnexus-ai-targets', [
			'msg' => 'target_deleted',
		]);

		wp_safe_redirect($url);
		exit;
	}

	public function test(): void {
		if (!current_user_can(Admin::CAPABILITY)) {
			wp_die(esc_html__('Sorry, you are not allowed to access this page.'));
		}

		check_admin_referer('wpnexus_ai_target_test');

		$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
		if ($id <= 0) {
			wp_safe_redirect(Admin::url('wpnexus-ai-targets', ['msg' => 'target_test_failed']));
			exit;
		}

		$target = $this->repo->get($id);
		if (!$target) {
			wp_safe_redirect(Admin::url('wpnexus-ai-targets', ['msg' => 'target_test_failed']));
			exit;
		}

		// Use BridgeClient instead of manual wp_remote_get
		$client = new BridgeClient();
		$resp = $client->health($target);

		$result = [
			'ok' => (bool) $resp->ok,
			'status' => $resp->status,
			'error' => $resp->error,
			'body' => is_array($resp->json) ? $resp->json : $resp->raw,
			'bridge_detected' => false,
		];

		// Bridge detected heuristic: 200 + JSON payload
		if ($resp->ok && is_array($resp->json)) {
			$result['bridge_detected'] = true;
		}

		$this->logger->info('targets.test.done', [
			'id' => $id,
			'ok' => $result['ok'],
			'status' => $result['status'],
			'bridge_detected' => $result['bridge_detected'],
			'error' => $result['error'],
		]);

		$key = 'wpnexus_ai_target_test_' . get_current_user_id() . '_' . $id;
		set_transient($key, $result, 60);

		$url = Admin::url('wpnexus-ai-targets', [
			'action' => 'edit',
			'id' => $id,
			'msg' => $result['ok'] ? 'target_test_ok' : 'target_test_failed',
			'test' => 1,
		]);

		wp_safe_redirect($url);
		exit;
	}
}
