<?php
namespace WPNexusAI\Queue\Tasks;

use WP_Error;
use WPNexusAI\Logging\Logger;
use WPNexusAI\DB\Repos\JobsRepo;
use WPNexusAI\DB\Repos\TargetsRepo;
use WPNexusAI\DB\Repos\RegistryRepo;
use WPNexusAI\Bridge\Client\BridgeClient;
use WPNexusAI\Engine\Sync\Signature;

if (!defined('ABSPATH')) {
	exit;
}

final class DeleteTask implements TaskInterface {

	/** @var Logger */
	private $logger;

	/** @var JobsRepo */
	private $jobs;

	public function __construct() {
		$this->logger = Logger::instance();
		$this->jobs   = new JobsRepo();
	}

	public function type(): string {
		return 'delete';
	}

	public function handle(array $job_row): TaskResult {
		$job_id  = isset($job_row['id']) ? (int) $job_row['id'] : 0;
		$payload = $this->jobs->decode_payload($job_row);

		$source_post_id = isset($payload['source_post_id']) ? (int) $payload['source_post_id'] : (isset($payload['post_id']) ? (int) $payload['post_id'] : 0);
		$target_id      = isset($payload['target_id']) ? (int) $payload['target_id'] : 0;
		$lang           = isset($payload['language_code']) ? sanitize_key((string) $payload['language_code']) : (isset($payload['target_lang']) ? sanitize_key((string) $payload['target_lang']) : '');

		$mode = isset($payload['delete_mode']) ? sanitize_key((string) $payload['delete_mode']) : 'trash';
		if (!in_array($mode, ['trash','delete','unlink'], true)) {
			$mode = 'trash';
		}

		$this->logger->info('task.delete.start', [
			'job_id'         => $job_id,
			'source_post_id' => $source_post_id,
			'target_id'      => $target_id,
			'lang'           => $lang,
			'mode'           => $mode,
		]);

		if ($source_post_id <= 0 || $target_id <= 0 || $lang === '') {
			return TaskResult::failed(t('delete_err_invalid_payload'));
		}

		$targets_repo = new TargetsRepo();
		$target_row   = $targets_repo->get($target_id);

		if (!$target_row) {
			return TaskResult::failed(t('delete_err_target_missing'));
		}

		$registry = new RegistryRepo();
		$link     = $registry->get_link($source_post_id, $target_id, $lang);

		// UNLINK: no remote call.
		if ($mode === 'unlink') {
			$registry->upsert_link($source_post_id, $target_id, $lang, [
				'state'      => 'unlinked',
				'last_error' => null,
			]);

			$payload['delete'] = [
				'mode'   => 'unlink',
				'action' => 'unlinked',
			];
			if (method_exists($this->jobs, 'update_payload')) {
				$this->jobs->update_payload($job_id, $payload);
			}

			$this->logger->info('task.delete.done.unlink', [
				'job_id' => $job_id,
			]);

			return TaskResult::done();
		}

		$remote_post_id = $link && !empty($link['remote_post_id']) ? (string) $link['remote_post_id'] : '';
		$signature      = Signature::for_link($source_post_id, $target_id, $lang);

		$delete_payload = [
			'mode'      => $mode,       // trash|delete
			'signature' => $signature,  // always include signature
		];
		if ($remote_post_id !== '') {
			$delete_payload['remote_post_id'] = $remote_post_id;
		}

		$client = new BridgeClient();
		$res    = $client->posts_delete($target_row, $delete_payload);

		if (!$res->ok) {
			$status = is_int($res->status) ? $res->status : 0;
			$err    = $res->error ? (string) $res->error : t('delete_err_bridge_http');

			$this->logger->warning('task.delete.bridge.fail', [
				'job_id' => $job_id,
				'status' => $status,
				'error'  => $err,
			]);

			$registry->upsert_link($source_post_id, $target_id, $lang, [
				'state'      => 'failed',
				'last_error' => $err,
			]);

			// Retry on network/5xx
			if ($status === 0 || $status >= 500) {
				return TaskResult::retry(time() + 120, $err);
			}

			// Bridge missing endpoint/plugin -> needs input
			if ($status === 404) {
				return TaskResult::needs_input(t('delete_err_bridge_missing'));
			}

			return TaskResult::failed($err);
		}

		$json   = is_array($res->json) ? $res->json : [];
		$action = isset($json['action']) ? (string) $json['action'] : 'deleted';

		$registry->upsert_link($source_post_id, $target_id, $lang, [
			'state'      => 'deleted',
			'last_error' => null,
		]);

		$payload['delete'] = [
			'mode'   => $mode,
			'action' => $action,
		];

		if (method_exists($this->jobs, 'update_payload')) {
			$this->jobs->update_payload($job_id, $payload);
		}

		$this->logger->info('task.delete.done', [
			'job_id' => $job_id,
			'action' => $action,
			'mode'   => $mode,
		]);

		return TaskResult::done();
	}
}
