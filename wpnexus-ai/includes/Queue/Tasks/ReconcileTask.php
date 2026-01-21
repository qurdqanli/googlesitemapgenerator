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
 *
 * Heavy work stays in queue. Admin request only creates one reconcile job.
 */
final class ReconcileTask implements TaskInterface {

	/** @var Logger */
	private $logger;

	/** @var JobsRepo */
	private $jobs;

	public function __construct() {
		$this->logger = Logger::instance();
		$this->jobs   = new JobsRepo();
	}

	public function type(): string {
		return 'reconcile';
	}

	public function handle(array $job_row): TaskResult {
		$job_id  = isset($job_row['id']) ? (int) $job_row['id'] : 0;
		$payload = $this->jobs->decode_payload($job_row);

		$mode = isset($payload['mode']) ? sanitize_key((string) $payload['mode']) : 'registry';
		if (!in_array($mode, ['registry', 'bulk'], true)) {
			$mode = 'registry';
		}

		$cursor = isset($payload['cursor']) ? max(0, (int) $payload['cursor']) : 0;
		$chunk  = isset($payload['chunk']) ? max(5, min(200, (int) $payload['chunk'])) : 25;

		$this->logger->info('task.reconcile.start', [
			'job_id'  => $job_id,
			'mode'    => $mode,
			'cursor'  => $cursor,
			'chunk'   => $chunk,
		]);

		if ($mode === 'bulk') {
			return $this->handle_bulk($job_id, $payload, $cursor, $chunk);
		}

		return $this->handle_registry($job_id, $payload, $cursor, $chunk);
	}

	/**
	 * Bulk: post_ids x targets -> enqueue upserts (not forced by default).
	 */
	private function handle_bulk(int $job_id, array $payload, int $cursor, int $chunk): TaskResult {
		$post_ids = isset($payload['post_ids']) && is_array($payload['post_ids']) ? $payload['post_ids'] : [];
		$post_ids = array_values(array_filter(array_map('intval', $post_ids), function ($v) { return $v > 0; }));

		$target_ids = [];
		if (!empty($payload['target_ids']) && is_array($payload['target_ids'])) {
			$target_ids = array_values(array_filter(array_map('intval', $payload['target_ids']), function ($v) { return $v > 0; }));
		}

		$targets_repo = new TargetsRepo();
		if (empty($target_ids)) {
			$targets = $targets_repo->list(200);
			foreach ($targets as $tr) {
				$tid = (int) ($tr['id'] ?? 0);
				if ($tid > 0) {
					$target_ids[] = $tid;
				}
			}
		}

		$post_count   = count($post_ids);
		$target_count = count($target_ids);

		if ($post_count === 0 || $target_count === 0) {
			return TaskResult::failed(t('reconcile_err_nothing_to_do'));
		}

		$total = $post_count * $target_count;
		$end   = min($total, $cursor + $chunk);

		$dispatcher = new Dispatcher();

		for ($i = $cursor; $i < $end; $i++) {
			$p_index = (int) floor($i / $target_count);
			$t_index = (int) ($i % $target_count);

			$pid = (int) ($post_ids[$p_index] ?? 0);
			$tid = (int) ($target_ids[$t_index] ?? 0);

			if ($pid <= 0 || $tid <= 0) {
				continue;
			}

			$job_payload = [
				'source_post_id' => $pid,
				'target_id'      => $tid,
				'language_code'  => 'auto', // T14 UpsertTask will resolve to real lang
				'force'          => 0,      // bulk is normal sync; reconcile registry uses force
			];

			// Changed from JobsRepo->create to Dispatcher->enqueue to ensure scheduling
			$job_payload['post_id'] = $pid; // alias for TranslateTask
			$job_payload['chain_upsert'] = 1;

			$jid = $dispatcher->enqueue('translate', $job_payload, null);

			$this->logger->info('reconcile.bulk.enqueued', [
				'job_id'  => $job_id,
				'translate'  => $jid,
				'post_id' => $pid,
				'target'  => $tid,
			]);
		}

		$payload['cursor'] = $end;
		$this->jobs->update_payload($job_id, $payload);

		if ($end >= $total) {
			$this->logger->info('task.reconcile.done', [
				'job_id' => $job_id,
				'mode'   => 'bulk',
				'total'  => $total,
			]);
			return TaskResult::done();
		}

		$this->logger->info('task.reconcile.progress', [
			'job_id'  => $job_id,
			'mode'    => 'bulk',
			'cursor'  => $end,
			'total'   => $total,
		]);

		return TaskResult::retry(time() + 5, 'Continue bulk reconcile');
	}

	/**
	 * Registry: iterate registry linked/failed and enqueue forced upsert (re-create if missing).
	 */
	private function handle_registry(int $job_id, array $payload, int $cursor, int $chunk): TaskResult {
		$target_id = isset($payload['target_id']) ? (int) $payload['target_id'] : 0;

		$registry = new RegistryRepo();
		$rows = $registry->list_for_reconcile($chunk, $cursor, $target_id);

		if (empty($rows)) {
			$this->logger->info('task.reconcile.done', [
				'job_id' => $job_id,
				'mode'   => 'registry',
			]);
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
			$job_payload['post_id'] = $source_post_id; // alias for TranslateTask
			$job_payload['chain_upsert'] = 1;

			$jid = $dispatcher->enqueue('translate', $job_payload, null);
			$count++;

			$this->logger->info('reconcile.registry.enqueued', [
				'job_id'         => $job_id,
				'translate'      => $jid,
				'source_post_id' => $source_post_id,
				'target_id'      => $tid,
				'lang'           => $lang,
			]);
		}

		$payload['cursor'] = $cursor + $chunk;
		$this->jobs->update_payload($job_id, $payload);

		$this->logger->info('task.reconcile.progress', [
			'job_id' => $job_id,
			'mode'   => 'registry',
			'count'  => $count,
			'next'   => $payload['cursor'],
		]);

		return TaskResult::retry(time() + 5, 'Continue registry reconcile');
	}
}
