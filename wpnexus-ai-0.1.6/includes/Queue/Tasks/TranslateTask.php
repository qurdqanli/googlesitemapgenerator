<?php
namespace WPNexusAI\Queue\Tasks;

use WP_Error;
use WPNexusAI\Logging\Logger;
use WPNexusAI\DB\Repos\JobsRepo;
use WPNexusAI\DB\Repos\TargetsRepo;
use WPNexusAI\Engine\Extract\Extractor;
use WPNexusAI\Engine\Extract\Segmenter;
use WPNexusAI\Engine\Routing\LanguageRouter;
use WPNexusAI\Engine\Routing\LanguageResolver;
use WPNexusAI\Providers\ProviderChain;
use WPNexusAI\Providers\TranslateRequest;
use WPNexusAI\Queue\Dispatcher;
use WPNexusAI\Util\Lang;

if (!defined('ABSPATH')) {
	exit;
}

final class TranslateTask implements TaskInterface {

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
		return 'translate';
	}

	/**
	 * TaskInterface entrypoint. JobRunner calls handle($job_row).
	 * We keep run($job_id, $job_row) for backward compatibility.
	 *
	 * @param array<string,mixed> $job_row
	 */
	public function handle(array $job_row): TaskResult {
		$job_id = isset($job_row['id']) ? (int) $job_row['id'] : 0;
		return $this->run($job_id, $job_row);
	}

	/**
	 * @param array<string,mixed> $job_row
	 */
	public function run(int $job_id, array $job_row): TaskResult {
		$payload = $this->jobs->decode_payload($job_row);

		// Post ID is required
		$post_id = isset($payload['source_post_id']) ? (int) $payload['source_post_id'] : (isset($payload['post_id']) ? (int) $payload['post_id'] : 0);
		$chain_upsert = !empty($payload['chain_upsert']);

		// ---------------------------------------------------------
		// START: Meta Routing + Auto Resolve + Send Original Logic
		// ---------------------------------------------------------
		$target_id = isset($payload['target_id']) ? (int) $payload['target_id'] : 0;

		if ($target_id > 0) {
			$targets_repo = new TargetsRepo();
			$target_row   = $targets_repo->get($target_id);

			if ($target_row) {
				$router = new LanguageRouter();
				// Routing logic: determines source_lang, preferences, and send_original
				$route  = $router->route($post_id, $target_row, $payload);

				$send_original = (bool) ($route['send_original'] ?? false);
				$source_lang   = (string) ($route['source_lang'] ?? 'auto');
				$lang_pref     = (string) ($route['language_pref'] ?? 'auto');

				// Resolve the target language
				$resolver    = new LanguageResolver();
				$target_lang = $resolver->resolve($target_row, $lang_pref);

				// Update payload with resolved values for chaining/debugging
				$payload['send_original'] = $send_original ? 1 : 0;
				$payload['source_lang']   = $source_lang;
				$payload['target_lang']   = $target_lang;
				$payload['language_code'] = $target_lang;
				$this->jobs->update_payload($job_id, $payload);

				// If send_original is enabled, skip translation and optionally chain to upsert.
				if ($send_original) {
					$payload['translation'] = [
						'provider'     => '',
						'key_id'       => 0,
						'translations' => [],
						'usage'        => [],
						'note'         => 'send_original enabled; translation skipped',
					];
					$this->jobs->update_payload($job_id, $payload);

					if ($chain_upsert) {
						$next_payload = $payload;
						$next_payload['source_post_id'] = $post_id;
						$next_payload['post_id']        = $post_id;

						$next_job_id = (new Dispatcher())->enqueue('upsert', $next_payload, null);

						$payload['chained_upsert_job_id'] = $next_job_id;
						$this->jobs->update_payload($job_id, $payload);

						$this->logger->info('task.translate.chain.upsert', [
							'job_id'      => $job_id,
							'next_job_id' => $next_job_id,
							'note'        => 'send_original',
						]);
					}

					return TaskResult::done();
				}
			}
		}
		// ---------------------------------------------------------
		// END: Meta Routing + Auto Resolve + Send Original Logic
		// ---------------------------------------------------------

		// Re-read languages from payload (routing might have changed them)
		$source_lang = isset($payload['source_lang']) ? sanitize_key((string) $payload['source_lang']) : '';
		$target_lang = isset($payload['target_lang']) ? sanitize_key((string) $payload['target_lang']) : '';

		$this->logger->info('task.translate.start', [
			'job_id'      => $job_id,
			'post_id'     => $post_id,
			'target_id'   => $target_id,
			'source_lang' => $source_lang,
			'target_lang' => $target_lang,
		]);

		// Validation
		if ($post_id <= 0 || $target_lang === '') {
			return TaskResult::failed('TranslateTask missing post_id or target_lang.');
		}

		// Extract content
		$extracted = (new Extractor())->extract($post_id);
		if (is_wp_error($extracted)) {
			return TaskResult::failed('Extract failed: ' . $extracted->get_error_message());
		}

		// Segmentation
		$segments = (new Segmenter())->segments($extracted);
		if (empty($segments)) {
			$payload['translation'] = [
				'provider'     => '',
				'key_id'       => 0,
				'translations' => [],
				'usage'        => [],
				'note'         => 'No segments to translate.',
			];
			$this->jobs->update_payload($job_id, $payload);

			// Optional: chain to UpsertTask
			if ($chain_upsert) {
				$next_payload = $payload;
				$next_payload['source_post_id'] = $post_id;
				$next_payload['post_id']        = $post_id;

				$next_job_id = (new Dispatcher())->enqueue('upsert', $next_payload, null);

				$payload['chained_upsert_job_id'] = $next_job_id;
				$this->jobs->update_payload($job_id, $payload);

				$this->logger->info('task.translate.chain.upsert', [
					'job_id'      => $job_id,
					'next_job_id' => $next_job_id,
					'note'        => 'no_segments',
				]);
			}

			return TaskResult::done();
		}

		// Normalize languages
		$source_lang = Lang::normalize($source_lang !== '' ? $source_lang : 'auto');
		$target_lang = Lang::normalize($target_lang);

		
		// Skip translation if target language equals source language.
		// This avoids wasting provider calls and prevents jobs failing on providers when translation isn't needed.
		if ($target_lang === '' || ($source_lang !== 'auto' && $target_lang === $source_lang)) {
			$payload['translation'] = [
				'provider'     => '',
				'key_id'       => 0,
				'translations' => [],
				'usage'        => [],
				'note'         => $target_lang === '' ? 'Target language empty; translation skipped.' : 'Source and target languages match; translation skipped.',
			];
			$this->jobs->update_payload($job_id, $payload);

			$this->logger->info('task.translate.skip.same_language', [
				'job_id'      => $job_id,
				'source_lang' => $source_lang,
				'target_lang' => $target_lang,
			]);

			// Optional: chain to UpsertTask
			if ($chain_upsert) {
				$next_payload = $payload;
				$next_payload['source_post_id'] = $post_id;
				$next_payload['post_id']        = $post_id;

				$next_job_id = (new Dispatcher())->enqueue('upsert', $next_payload, null);

				$payload['chained_upsert_job_id'] = $next_job_id;
				$this->jobs->update_payload($job_id, $payload);

				$this->logger->info('task.translate.chain.upsert', [
					'job_id'      => $job_id,
					'next_job_id' => $next_job_id,
					'note'        => 'same_language',
				]);
			}

			return TaskResult::done();
		}


		// Context (optional)
		$context = [
			'post_type' => (string) (($extracted['post']['post_type'] ?? '') ?: ''),
			'post_id'   => $post_id,
		];

		// Prepare translate request
		$req = new TranslateRequest(
			$source_lang !== '' ? $source_lang : 'auto',
			$target_lang,
			$segments,
			$context
		);

		// Execute Provider Chain
		$res = (new ProviderChain())->translate($req);

		if ($res instanceof WP_Error) {
			$code = (string) $res->get_error_code();
			$data = $res->get_error_data();

			$this->logger->warning('task.translate.fail', [
				'job_id' => $job_id,
				'code'   => $code,
			]);

			// Rate limit: retry later
			if ($code === 'wpnexus_provider_rate_limited') {
				$retry_after = 60;
				if (is_array($data) && isset($data['retry_after'])) {
					$retry_after = max(15, (int) $data['retry_after']);
				}
				return TaskResult::retry(time() + $retry_after, (string) $res->get_error_message());
			}

			// All keys cooldown or missing keys -> needs input
			if ($code === 'wpnexus_provider_no_keys' || $code === 'wpnexus_provider_all_rate_limited') {
				return TaskResult::needs_input((string) $res->get_error_message());
			}

			return TaskResult::failed((string) $res->get_error_message());
		}

		// Save successful result
		$payload['translation'] = $res->to_array();
		$this->jobs->update_payload($job_id, $payload);

		$this->logger->info('task.translate.done', [
			'job_id'   => $job_id,
			'provider' => $res->provider,
			'key_id'   => $res->key_id,
			'count'    => count($res->translations),
		]);

		// Optional: chain to UpsertTask
		if ($chain_upsert) {
			$next_payload = $payload;
			$next_payload['source_post_id'] = $post_id;
			$next_payload['post_id']        = $post_id;

			$next_job_id = (new Dispatcher())->enqueue('upsert', $next_payload, null);

			$payload['chained_upsert_job_id'] = $next_job_id;
			$this->jobs->update_payload($job_id, $payload);

			$this->logger->info('task.translate.chain.upsert', [
				'job_id'      => $job_id,
				'next_job_id' => $next_job_id,
			]);
		}

		return TaskResult::done();
	}
}
