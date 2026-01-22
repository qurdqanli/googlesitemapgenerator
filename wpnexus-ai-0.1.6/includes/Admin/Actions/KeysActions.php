<?php
namespace WPNexusAI\Admin\Actions;

use WPNexusAI\Admin\Admin;
use WPNexusAI\DB\Repos\KeysRepo;
use WPNexusAI\Logging\Logger;

if (!defined('ABSPATH')) {
	exit;
}

final class KeysActions {

	/** @var Logger */
	private $logger;

	/** @var KeysRepo */
	private $repo;

	public function __construct() {
		$this->logger = Logger::instance();
		$this->repo = new KeysRepo();
	}

	public function init(): void {
		add_action('admin_post_wpnexus_ai_key_create', [$this, 'create']);
		add_action('admin_post_wpnexus_ai_key_update', [$this, 'update']);
		add_action('admin_post_wpnexus_ai_key_delete', [$this, 'delete']);
		add_action('admin_post_wpnexus_ai_key_toggle', [$this, 'toggle']);
		add_action('admin_post_wpnexus_ai_key_bulk_import', [$this, 'bulk_import']);
	}

	public function create(): void {
		$this->guard();
		check_admin_referer('wpnexus_ai_key_create');

		$provider = isset($_POST['provider']) ? sanitize_key((string) wp_unslash($_POST['provider'])) : '';
		$key = isset($_POST['key']) ? trim((string) wp_unslash($_POST['key'])) : '';
		$is_active = isset($_POST['is_active']) ? (bool) (int) $_POST['is_active'] : true;

		if ($provider === '' || $key === '') {
			$this->logger->warning('keys.admin.create.invalid', ['provider' => $provider]);
			$this->redirect(['msg' => 'key_create_failed']);
		}

		if ($this->repo->exists_plain($provider, $key)) {
			$this->logger->info('keys.admin.create.duplicate', ['provider' => $provider]);
			$this->redirect(['msg' => 'key_duplicate']);
		}

		$id = $this->repo->create($provider, $key, $is_active);

		$this->logger->info('keys.admin.create.done', ['id' => $id, 'provider' => $provider]);

		$this->redirect(['msg' => $id > 0 ? 'key_created' : 'key_create_failed']);
	}

	public function update(): void {
		$this->guard();
		check_admin_referer('wpnexus_ai_key_update');

		$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
		$key = isset($_POST['key']) ? trim((string) wp_unslash($_POST['key'])) : '';

		if ($id <= 0 || $key === '') {
			$this->logger->warning('keys.admin.update.invalid', ['id' => $id]);
			$this->redirect(['msg' => 'key_update_failed']);
		}

		$ok = $this->repo->update_key($id, $key);

		$this->logger->info('keys.admin.update.done', ['id' => $id, 'ok' => $ok]);

		$this->redirect(['msg' => $ok ? 'key_updated' : 'key_update_failed']);
	}

	public function delete(): void {
		$this->guard();
		check_admin_referer('wpnexus_ai_key_delete');

		$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
		if ($id <= 0) {
			$this->redirect(['msg' => 'key_delete_failed']);
		}

		$ok = $this->repo->delete($id);

		$this->redirect(['msg' => $ok ? 'key_deleted' : 'key_delete_failed']);
	}

	public function toggle(): void {
		$this->guard();
		check_admin_referer('wpnexus_ai_key_toggle');

		$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
		$active = isset($_POST['active']) ? (bool) (int) $_POST['active'] : true;

		if ($id <= 0) {
			$this->redirect(['msg' => 'key_toggle_failed']);
		}

		$ok = $this->repo->set_active($id, $active);

		$this->redirect(['msg' => $ok ? 'key_toggled' : 'key_toggle_failed']);
	}

	public function bulk_import(): void {
		$this->guard();
		check_admin_referer('wpnexus_ai_key_bulk_import');

		$provider = isset($_POST['provider']) ? sanitize_key((string) wp_unslash($_POST['provider'])) : '';
		$raw = isset($_POST['keys']) ? (string) wp_unslash($_POST['keys']) : '';
		$is_active = isset($_POST['is_active']) ? (bool) (int) $_POST['is_active'] : true;

		if ($provider === '' || trim($raw) === '') {
			$this->redirect(['msg' => 'key_import_failed']);
		}

		$lines = preg_split('/\r\n|\r|\n/', $raw);
		$lines = is_array($lines) ? $lines : [];

		$added = 0;
		$skipped = 0;

		$this->logger->info('keys.admin.import.start', [
			'provider' => $provider,
			'lines' => count($lines),
			'is_active' => $is_active,
		]);

		foreach ($lines as $line) {
			$key = trim((string) $line);
			if ($key === '') {
				continue;
			}

			if ($this->repo->exists_plain($provider, $key)) {
				$skipped++;
				continue;
			}

			$id = $this->repo->create($provider, $key, $is_active);
			if ($id > 0) {
				$added++;
			}
		}

		$this->logger->info('keys.admin.import.done', [
			'provider' => $provider,
			'added' => $added,
			'skipped' => $skipped,
		]);

		$this->redirect([
			'msg' => 'key_imported',
			'added' => $added,
			'skipped' => $skipped,
		]);
	}

	private function guard(): void {
		if (!current_user_can(Admin::CAPABILITY)) {
			wp_die(esc_html__('Sorry, you are not allowed to access this page.'));
		}
	}

	private function redirect(array $args): void {
		$url = Admin::url('wpnexus-ai-keys', $args);
		wp_safe_redirect($url);
		exit;
	}
}
