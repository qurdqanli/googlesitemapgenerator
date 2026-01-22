<?php
namespace WPNexusAI\Queue\Tasks;

use WP_Error;
use WPNexusAI\Logging\Logger;
use WPNexusAI\DB\Repos\JobsRepo;
use WPNexusAI\DB\Repos\TargetsRepo;
use WPNexusAI\DB\Repos\RegistryRepo;
use WPNexusAI\Bridge\Client\BridgeClient;
use WPNexusAI\Engine\Extract\Extractor;
use WPNexusAI\Engine\Sync\TranslationApplier;
use WPNexusAI\Engine\Sync\UpsertPayloadBuilder;
use WPNexusAI\Engine\Sync\MediaSync;
use WPNexusAI\Util\ContentHash;
use WPNexusAI\Engine\Routing\LanguageRouter;
use WPNexusAI\Engine\Routing\LanguageResolver;

if (!defined('ABSPATH')) {
	exit;
}

final class UpsertTask implements TaskInterface {

	/** @var Logger */
	private $logger;

	/** @var JobsRepo */
	private $jobs;

	public function __construct() {
		$this->logger = Logger::instance();
		$this->jobs   = new JobsRepo();
	}

	public function type(): string {
		return 'upsert';
	}

	/**
	 * @param array<string,mixed> $job_row
	 */
	public function handle(array $job_row): TaskResult {
		$job_id  = isset($job_row['id']) ? (int) $job_row['id'] : 0;
		$payload = $this->jobs->decode_payload($job_row);

		$source_post_id = isset($payload['source_post_id']) ? (int) $payload['source_post_id'] : (isset($payload['post_id']) ? (int) $payload['post_id'] : 0);
		$target_id      = isset($payload['target_id']) ? (int) $payload['target_id'] : 0;

		// Initial lang extraction (will be overwritten by Router/Resolver below)
		$lang = isset($payload['language_code'])
			? sanitize_key((string) $payload['language_code'])
			: (isset($payload['target_lang']) ? sanitize_key((string) $payload['target_lang']) : '');

		$send_original = !empty($payload['send_original']);

		$this->logger->info('task.upsert.start', [
			'job_id'         => $job_id,
			'source_post_id' => $source_post_id,
			'target_id'      => $target_id,
			'lang'           => $lang,
			'send_original'  => $send_original ? 1 : 0,
		]);

		if ($source_post_id <= 0 || $target_id <= 0) {
			return TaskResult::failed(t('sync_err_invalid_payload'));
		}

		$targets_repo = new TargetsRepo();
		$target_row   = $targets_repo->get($target_id);

		if (!$target_row) {
			return TaskResult::failed(t('sync_err_target_missing'));
		}

		// ---------------------------------------------------------
		// Routing & Auto Resolve Logic
		// ---------------------------------------------------------
		$router = new LanguageRouter();
		$route  = $router->route($source_post_id, $target_row, $payload);

		$send_original = (bool) ($route['send_original'] ?? false);
		$source_lang   = (string) ($route['source_lang'] ?? 'auto');
		$lang_pref     = (string) ($route['language_pref'] ?? 'auto');

		$resolver = new LanguageResolver();
		$lang     = $resolver->resolve($target_row, $lang_pref);

		// Ensure payload reflects resolved values (helpful for retries/debug)
		$payload['send_original'] = $send_original ? 1 : 0;
		$payload['source_lang']   = $source_lang;
		$payload['language_code'] = $lang;

		if (method_exists($this->jobs, 'update_payload')) {
			$this->jobs->update_payload($job_id, $payload);
		}

		// Check resolved lang validity
		if ($lang === '') {
			return TaskResult::failed('Language resolution failed (empty language code).');
		}

		$registry = new RegistryRepo();
		$link     = $registry->get_link($source_post_id, $target_id, $lang);

		$extracted = (new Extractor())->extract($source_post_id);
		if ($extracted instanceof WP_Error) {
			$registry->upsert_link($source_post_id, $target_id, $lang, [
				'state'      => 'failed',
				'last_error' => 'Extract failed: ' . $extracted->get_error_message(),
			]);
			return TaskResult::failed('Extract failed: ' . $extracted->get_error_message());
		}

		if (!is_array($extracted)) {
			$registry->upsert_link($source_post_id, $target_id, $lang, [
				'state'      => 'failed',
				'last_error' => 'Extract failed: invalid extract result',
			]);
			return TaskResult::failed('Extract failed: invalid extract result');
		}

		// Apply translation if present and not "send_original".
		if (
			!$send_original
			&& !empty($payload['translation']['translations'])
			&& is_array($payload['translation']['translations'])
		) {
			$extracted = (new TranslationApplier())->apply($extracted, $payload['translation']['translations']);
			$this->logger->debug('task.upsert.translation.applied', [
				'job_id' => $job_id,
			]);
		}

		// ---------------------------------------------------------
		// Media sync (featured + inline) and URL rewrite
		// ---------------------------------------------------------
		try {
			$this->logger->info('task.upsert.media.enter', ['job_id' => $job_id]);

			$media_res = (new \WPNexusAI\Engine\Sync\MediaSync())->sync(
				$source_post_id,
				$target_row,
				$lang,
				$extracted,
				$payload
			);

			$this->logger->info('task.upsert.media.exit', [
				'job_id' => $job_id,
				'ok'     => is_array($media_res),
				'wp_err' => is_wp_error($media_res),
			]);
		} catch (\Throwable $e) {
			// Catching Throwable handles Errors and Exceptions while preventing process crash
			$this->logger->error('task.upsert.media.throwable', [
				'job_id' => $job_id,
				'msg'    => $e->getMessage(),
				'file'   => $e->getFile(),
				'line'   => $e->getLine(),
			]);

			// Fallback: allow post creation to proceed without processed media
			$media_res = null;
		}

		if (is_wp_error($media_res)) {
			$this->logger->warning('task.upsert.media.fail', [
				'job_id' => $job_id,
				'code'   => (string) $media_res->get_error_code(),
			]);

			// Hotfix: do not block upsert even if media processing fails
			$media_res = null;
		}

		if (is_array($media_res)) {
			if (!empty($media_res['content']) && is_string($media_res['content'])) {
				$extracted['content'] = (string) $media_res['content'];
			}
			if (!empty($media_res['featured_image_id'])) {
				$payload['featured_image_id'] = (int) $media_res['featured_image_id'];
			}
		}

		$mapped_terms = null;

		if (!empty($extracted['terms']) && is_array($extracted['terms'])) {
			$mapper = new \WPNexusAI\Engine\Mapping\TermsMapper();
			$mapped_terms = $mapper->map_terms($extracted['terms'], $target_row, $lang, $payload);

			if (is_wp_error($mapped_terms)) {
				$code = $mapped_terms->get_error_code();

				if ($code === 'wpnexus_terms_bridge_missing') {
					return TaskResult::needs_input(t('mapping_err_bridge_missing'));
				}

				$data   = $mapped_terms->get_error_data();
				$status = is_array($data) && isset($data['status']) ? (int) $data['status'] : 0;
				if ($status === 0 || $status >= 500 || $status === 429) {
					return TaskResult::retry(time() + 120, $mapped_terms->get_error_message());
				}

				return TaskResult::failed($mapped_terms->get_error_message());
			}
		}

		$builder      = new UpsertPayloadBuilder();
		$post_payload = $builder->build($extracted, $target_row, $lang, $payload, $link);

		if (is_array($mapped_terms) && !empty($mapped_terms)) {
			$post_payload['terms'] = $mapped_terms;
		}

		$hash = ContentHash::hash($builder->hashable($post_payload));

		$prev_hash  = $link && isset($link['content_hash']) ? (string) $link['content_hash'] : '';
		$prev_state = $link && isset($link['state']) ? (string) $link['state'] : '';
		$force      = !empty($payload['force']);

		$this->logger->debug('task.upsert.force', [
			'job_id' => $job_id,
			'force'  => $force ? 1 : 0,
		]);

		// Idempotent skip: check if content hash remains unchanged
		if (!$force && $prev_state === 'linked' && $prev_hash !== '' && hash_equals($prev_hash, $hash)) {
			$registry->touch($source_post_id, $target_id, $lang);

			$this->logger->info('task.upsert.skip.nochange', [
				'job_id' => $job_id,
				'hash'   => $hash,
			]);

			$payload['upsert'] = [
				'skipped' => true,
				'note'    => t('sync_skipped_nochange'),
				'hash'    => $hash,
			];

			if (method_exists($this->jobs, 'update_payload')) {
				$this->jobs->update_payload($job_id, $payload);
			}

			return TaskResult::done();
		}

		$client = new BridgeClient();
		$res    = $client->posts_upsert($target_row, $post_payload);

		if (!$res->ok) {
			$status = is_int($res->status) ? $res->status : 0;
			$err    = $res->error ? (string) $res->error : t('sync_err_bridge_http');

			$this->logger->warning('task.upsert.bridge.fail', [
				'job_id' => $job_id,
				'status' => $status,
				'error'  => $err,
			]);

			$registry->upsert_link($source_post_id, $target_id, $lang, [
				'content_hash' => $hash,
				'state'        => ($status === 404 ? 'unlinked' : 'failed'),
				'last_error'   => $err,
			]);

			if ($status === 0 || $status >= 500 || $status === 429) {
				return TaskResult::retry(time() + 120, $err);
			}

			if ($status === 404) {
				return TaskResult::needs_input(t('sync_err_bridge_missing'));
			}

			return TaskResult::failed($err);
		}

		$json          = is_array($res->json) ? $res->json : [];
		$remote_post_id = isset($json['remote_post_id']) ? (string) $json['remote_post_id'] : '';
		$url            = isset($json['url']) ? (string) $json['url'] : '';

		if ($remote_post_id === '') {
			$this->logger->warning('task.upsert.bridge.bad_response', [
				'job_id' => $job_id,
			]);

			$registry->upsert_link($source_post_id, $target_id, $lang, [
				'content_hash' => $hash,
				'state'        => 'failed',
				'last_error'   => t('sync_err_bridge_bad_response'),
			]);

			return TaskResult::failed(t('sync_err_bridge_bad_response'));
		}

		$registry->upsert_link($source_post_id, $target_id, $lang, [
			'remote_post_id' => $remote_post_id,
			'url'            => $url,
			'content_hash'   => $hash,
			'state'          => 'linked',
			'last_error'     => null,
		]);

		$payload['upsert'] = [
			'skipped'        => false,
			'remote_post_id' => $remote_post_id,
			'url'            => $url,
			'hash'           => $hash,
			'action'         => isset($json['action']) ? (string) $json['action'] : '',
		];

		if (method_exists($this->jobs, 'update_payload')) {
			$this->jobs->update_payload($job_id, $payload);
		}

		$this->logger->info('task.upsert.done', [
			'job_id'         => $job_id,
			'remote_post_id' => $remote_post_id,
			'hash'           => $hash,
		]);

		return TaskResult::done();
	}
}
