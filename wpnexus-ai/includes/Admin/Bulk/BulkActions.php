<?php
namespace WPNexusAI\Admin\Bulk;

use WPNexusAI\Logging\Logger;
use WPNexusAI\Queue\Dispatcher;

if (!defined('ABSPATH')) {
	exit;
}

final class BulkActions {

	private const ACTION = 'wpnexus_ai_bulk_sync';

	/** @var Logger */
	private $logger;

	public function __construct() {
		$this->logger = Logger::instance();
	}

	public static function init(): void {
		$inst = new self();

		add_filter('bulk_actions-edit-post', [$inst, 'register']);
		add_filter('handle_bulk_actions-edit-post', [$inst, 'handle'], 10, 3);

		// Optional: enable for pages too
		add_filter('bulk_actions-edit-page', [$inst, 'register']);
		add_filter('handle_bulk_actions-edit-page', [$inst, 'handle'], 10, 3);

		add_action('admin_notices', [$inst, 'notice']);

		$inst->logger->info('admin.bulk.init.done');
	}

	public function register(array $actions): array {
		$actions[self::ACTION] = t('bulk_sync_label');
		return $actions;
	}

	public function handle(string $redirect_to, string $doaction, array $post_ids): string {
		if ($doaction !== self::ACTION) {
			return $redirect_to;
		}

		if (!current_user_can('edit_posts')) {
			return add_query_arg(['wpnexus_ai_bulk' => 'no_access'], $redirect_to);
		}

		$post_ids = array_values(array_filter(array_map('intval', $post_ids), function ($v) { return $v > 0; }));
		if (empty($post_ids)) {
			return add_query_arg(['wpnexus_ai_bulk' => 'empty'], $redirect_to);
		}

		
		$payload = [
			'mode'     => 'bulk',
			'post_ids' => $post_ids,
			// target_ids omitted => all targets
			'cursor'   => 0,
			'chunk'    => 25,
		];

		$dispatcher = new Dispatcher();
		$jid = $dispatcher->enqueue('reconcile', $payload, null);


		$this->logger->info('admin.bulk.created', [
			'reconcile_job_id' => $jid,
			'post_count'       => count($post_ids),
		]);

		return add_query_arg([
			'wpnexus_ai_bulk' => 'queued',
			'wpnexus_ai_job'  => $jid,
			'wpnexus_ai_n'    => count($post_ids),
		], $redirect_to);
	}

	public function notice(): void {
		if (!is_admin()) {
			return;
		}

		$flag = isset($_GET['wpnexus_ai_bulk']) ? sanitize_key((string) $_GET['wpnexus_ai_bulk']) : '';
		if ($flag === '') {
			return;
		}

		if ($flag === 'queued') {
			$jid = isset($_GET['wpnexus_ai_job']) ? (int) $_GET['wpnexus_ai_job'] : 0;
			$n   = isset($_GET['wpnexus_ai_n']) ? (int) $_GET['wpnexus_ai_n'] : 0;

			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html(sprintf(t('bulk_sync_queued'), max(0, $n), max(0, $jid)));
			echo '</p></div>';
			return;
		}

		if ($flag === 'empty') {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html(t('bulk_sync_empty')) . '</p></div>';
			return;
		}

		if ($flag === 'no_access') {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(t('bulk_sync_no_access')) . '</p></div>';
			return;
		}
	}
}
