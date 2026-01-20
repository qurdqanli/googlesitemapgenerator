<?php
namespace WPNexusAI\Queue\Tasks;

use WPNexusAI\Logging\Logger;
use WPNexusAI\DB\Repos\JobsRepo;
use WPNexusAI\DB\Repos\TargetsRepo;
use WPNexusAI\DB\Repos\RegistryRepo;
use WPNexusAI\Queue\Dispatcher;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Reconcile job:
 * - mode=bulk: given post_ids -> enqueue upsert per target (chunked)
 * - mode=registry: iterate registry (linked/failed) -> enqueue forced upsert
 */
final class ReconcileTask implements TaskInterface {

	/** @var Logger */
	private $logger;

	/** @var JobsRepo */
	private $jobs;

	/** @var TargetsRepo */
	private $targets;

	public function __construct() {
		$this->logger  = Logger::instance();
		$this->jobs    = new JobsRepo();
		$this->targets = new TargetsRepo();
	}

	public function type(): string {
		return 'reconcile';
	}

	/**
	 * @param array<string,mixed> $job_row
	 */
	public function run(int $job_id, array $job_row): TaskResult {
		$payload = $this->jobs->decode_payload($job_row);

		$mode = isset($payload['mode']) ? sanitize_key((string) $payload['mode']) : 'bulk';
		if (!in_array($mode, ['bulk','registry'], true)) {
			$mode = 'bulk';
		}

		$this->logger->info('task.reconcile.start', [
			'job_id' => $job_id,
			'mode'   => $mode,
		]);

		if ($mode === 'registry') {
			return $this->run_registry($job_id, $payload);
		}

		return $this->run_bulk($job_id, $payload);
	}

	/**
	 * Bulk: post_ids x targets -> enqueue upserts (not forced by default).
	 *
	 * @param array<string,mixed> $payload
	 */
	private function run_bulk(int $job_id, array $payload): TaskResult {
		$post_ids = isset($payload['post_ids']) && is_array($payload['post_ids']) ? $payload['post_ids'] : [];
		$post_ids = array_values(array_filter(array_map('intval', $post_ids)));

		if (empty($post_ids)) {
			return TaskResult::done();
		}

		$targets = $this->targets->list(2000);
		if (empty($targets)) {
			return TaskResult::done();
		}

		$dispatcher = new Dispatcher();
		$count = 0;

		foreach ($post_ids as $pid) {
			foreach ($targets as $tr) {
				$tid = (int) ($tr['id'] ?? 0);
				if ($tid <= 0) {
					continue;
				}

				$job_payload = [
					'source_post_id' => $pid,
					'target_id'      => $tid,
					'language_code'  => 'auto', // T14 UpsertTask will resolve to real lang
					'force'          => 0,      // bulk is normal sync; reconcile registry uses force
				];

				// Changed from JobsRepo->create to Dispatcher->enqueue to ensure scheduling
				$job_payload['chain_upsert'] = 1;
				$jid = $dispatcher->enqueue('translate', $job_payload, null);

				$this->logger->info('reconcile.bulk.enqueued', [
					'job_id'  => $job_id,
					'next_id' => $jid,
					'post_id' => $pid,
					'target'  => $tid,
				]);
				$count++;
			}
		}

		$this->logger->info('task.reconcile.bulk.done', [
			'job_id' => $job_id,
			'count'  => $count,
		]);

		return TaskResult::done();
	}

	/**
	 * Registry: iterate registry linked/failed and enqueue forced upsert (re-create if missing).
	 *
	 * @param array<string,mixed> $payload
	 */
	private function run_registry(int $job_id, array $payload): TaskResult {
		$registry = new RegistryRepo();
		$rows = $registry->list_for_reconcile(500);

		if (empty($rows)) {
			return TaskResult::done();
		}

		$dispatcher = new Dispatcher();
		$count = 0;

		foreach ($rows as $r) {
			$source_post_id = (int) ($r['source_post_id'] ?? 0);
			$tid            = (int) ($r['target_id'] ?? 0);
			$lang           = sanitize_key((string) ($r['language_code'] ?? ''));

			if ($source_post_id <= 0 || $tid <= 0 || $lang === '') {
				continue;
			}

			$job_payload = [
				'source_post_id' => $source_post_id,
				'target_id'      => $tid,
				'language_code'  => $lang,
				'force'          => 1, // important: do NOT skip on equal hash
			];

			// Changed from JobsRepo->create to Dispatcher->enqueue to ensure scheduling
			$job_payload['chain_upsert'] = 1;
			$jid = $dispatcher->enqueue('translate', $job_payload, null);
			$count++;

			$this->logger->info('reconcile.registry.enqueued', [
				'job_id'  => $job_id,
				'next_id' => $jid,
				'post_id' => $source_post_id,
				'target'  => $tid,
				'lang'    => $lang,
			]);
		}

		$this->logger->info('task.reconcile.registry.done', [
			'job_id' => $job_id,
			'count'  => $count,
		]);

		return TaskResult::done();
	}
}

