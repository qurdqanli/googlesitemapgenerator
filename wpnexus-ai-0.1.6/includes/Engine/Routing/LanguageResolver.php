<?php
namespace WPNexusAI\Engine\Routing;

use WPNexusAI\Logging\Logger;
use WPNexusAI\Bridge\Client\BridgeClient;
use WPNexusAI\Bridge\Contracts\Languages;
use WPNexusAI\Util\Lang;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Resolve actual target language code.
 *
 * Rules:
 * - explicit preference (non-auto) wins
 * - else target default_language if non-auto
 * - else Bridge /languages default/current
 * - else target fallback_language
 * - else 'en'
 */
final class LanguageResolver {

	/** @var Logger */
	private $logger;

	/** @var BridgeClient */
	private $bridge;

	public function __construct() {
		$this->logger = Logger::instance();
		$this->bridge = new BridgeClient();
	}

	public function resolve(array $target_row, string $preference): string {
		$preference = Lang::sanitize_code($preference);
		$target_default = isset($target_row['default_language']) ? Lang::sanitize_code((string) $target_row['default_language']) : '';
		$fallback = isset($target_row['fallback_language']) ? Lang::sanitize_code((string) $target_row['fallback_language']) : '';

		if ($preference !== '' && $preference !== 'auto') {
			return $preference;
		}

		if ($target_default !== '' && $target_default !== 'auto') {
			return $target_default;
		}

		// Auto mode: ask Bridge (background job only).
		$res = $this->bridge->languages($target_row);

		if ($res->ok && is_array($res->json)) {
			$contract = Languages::from_array($res->json);
			$code = $contract->default ?: ($contract->current ?: null);

			if (is_string($code) && $code !== '') {
				$code = Lang::sanitize_code($code);
				if ($code !== '' && $code !== 'auto') {
					$this->logger->info('lang.resolve.bridge.ok', [
						'target_id' => (int) ($target_row['id'] ?? 0),
						'lang'      => $code,
					]);
					return $code;
				}
			}
		} else {
			$this->logger->warning('lang.resolve.bridge.fail', [
				'target_id' => (int) ($target_row['id'] ?? 0),
				'status'    => (int) ($res->status ?? 0),
				'error'     => (string) ($res->error ?? ''),
			]);
		}

		if ($fallback !== '' && $fallback !== 'auto') {
			return $fallback;
		}

		return 'en';
	}
}
