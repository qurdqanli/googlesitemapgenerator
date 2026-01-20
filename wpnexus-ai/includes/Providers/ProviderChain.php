<?php
namespace WPNexusAI\Providers;

use WP_Error;
use WPNexusAI\Logging\Logger;
use WPNexusAI\Providers\Adapters\OpenAIAdapter;
use WPNexusAI\Providers\Adapters\ClaudeAdapter;
use WPNexusAI\Providers\Adapters\GeminiAdapter;
use WPNexusAI\Providers\Adapters\CustomAdapter;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Provider chain:
 * - Select key (weighted)
 * - Call adapter
 * - On 429 -> cooldown + retry with another key
 * - On failure -> try next provider
 */
final class ProviderChain {

	/** @var Logger */
	private $logger;

	/** @var KeySelector */
	private $keys;

	/** @var array<string,ProviderInterface> */
	private $providers = [];

	public function __construct() {
		$this->logger = Logger::instance();
		$this->keys   = new KeySelector();

		$this->providers = [
			'openai'  => new OpenAIAdapter(),
			'claude'  => new ClaudeAdapter(),
			'gemini'  => new GeminiAdapter(),
			'custom'  => new CustomAdapter(),
		];
	}

	/**
	 * @return TranslateResult|WP_Error
	 */
	public function translate(TranslateRequest $req) {
		$chain = apply_filters('wpnexus_ai_provider_chain', ['openai','claude','gemini'], $req);
		if (!is_array($chain) || empty($chain)) {
			$chain = ['openai','claude','gemini'];
		}

		$max_key_tries = (int) apply_filters('wpnexus_ai_provider_max_key_tries', 3, $req);

		$this->logger->info('providers.chain.start', [
			'from'  => $req->source_lang,
			'to'    => $req->target_lang,
			'count' => is_array($req->segments) ? count($req->segments) : 0,
			'chain' => $chain,
		]);

		$last_error = null;

		foreach ($chain as $provider_id) {
			$provider_id = sanitize_key((string) $provider_id);
			if ($provider_id === '' || empty($this->providers[$provider_id])) {
				continue;
			}

			$adapter = $this->providers[$provider_id];

			$this->logger->info('providers.chain.provider.start', [
				'provider' => $provider_id,
			]);

			for ($attempt = 1; $attempt <= $max_key_tries; $attempt++) {
				$selected = $this->keys->select($provider_id);
				if (is_wp_error($selected)) {
					$last_error = $selected;

					$this->logger->warning('providers.chain.key.select.fail', [
						'provider' => $provider_id,
						'code'     => $selected->get_error_code(),
					]);

					// No keys or all cooldown: try next provider.
					break;
				}

				$this->logger->debug('providers.chain.key.selected', [
					'provider' => $provider_id,
					'key_id'   => (int) $selected->id,
					'attempt'  => $attempt,
				]);

				$res = $adapter->translate($req, $selected);

				if ($res instanceof TranslateResult) {
					$this->keys->report_success((int) $selected->id);

					$this->logger->info('providers.chain.provider.ok', [
						'provider' => $provider_id,
						'key_id'   => (int) $selected->id,
					]);

					return $res;
				}

				if (is_wp_error($res)) {
					$last_error = $res;
					$code = $res->get_error_code();

					// 429 -> cooldown & retry another key
					if ($code === 'wpnexus_provider_429') {
						$backoff = $this->keys->report_429((int) $selected->id);

						$this->logger->warning('providers.chain.provider.429', [
							'provider' => $provider_id,
							'key_id'   => (int) $selected->id,
							'backoff'  => $backoff,
						]);

						// Try another key immediately, but expose retry_after for the job layer.
						if ($attempt >= $max_key_tries) {
							return new WP_Error(
								'wpnexus_provider_rate_limited',
								t('provider_err_rate_limited'),
								[
									'provider'    => $provider_id,
									'retry_after' => $backoff > 0 ? $backoff : 60,
								]
							);
						}

						continue;
					}

					$this->keys->report_fail((int) $selected->id);

					$this->logger->warning('providers.chain.provider.fail', [
						'provider' => $provider_id,
						'key_id'   => (int) $selected->id,
						'code'     => $code,
					]);

					// Non-429 -> try next provider
					break;
				}

				$last_error = new WP_Error('wpnexus_provider_unknown', 'Unknown provider result.');
				break;
			}
		}

		if ($last_error instanceof WP_Error) {
			// Normalize common key-layer errors to i18n messages for UI.
			$code = $last_error->get_error_code();
			if ($code === 'wpnexus_no_keys') {
				return new WP_Error('wpnexus_provider_no_keys', t('provider_err_no_keys'), $last_error->get_error_data());
			}
			if ($code === 'wpnexus_all_rate_limited') {
				return new WP_Error('wpnexus_provider_all_rate_limited', t('provider_err_all_rate_limited'), $last_error->get_error_data());
			}
			return $last_error;
		}

		return new WP_Error('wpnexus_provider_chain_failed', t('provider_err_api'));
	}
}
