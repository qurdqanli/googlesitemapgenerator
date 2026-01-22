<?php
namespace WPNexusAI\I18n;

use WPNexusAI\Logging\Logger;

if (!defined('ABSPATH')) {
	exit;
}

final class I18n {

	/** @var string */
	private $text_domain;

	/** @var string */
	private $plugin_dir;

	/** @var string */
	private $plugin_basename;

	public function __construct(string $text_domain, string $plugin_dir, string $plugin_basename) {
		$this->text_domain     = $text_domain;
		$this->plugin_dir      = rtrim($plugin_dir, '/\\') . DIRECTORY_SEPARATOR;
		$this->plugin_basename = $plugin_basename;
	}

	public function register(): void {
		add_action('init', function () {
			$logger = Logger::instance();
			$logger->info('i18n.load.start', [
				'text_domain' => $this->text_domain,
			]);

			load_plugin_textdomain(
				$this->text_domain,
				false,
				dirname($this->plugin_basename) . '/languages'
			);

			$logger->info('i18n.load.done', [
				'locale' => determine_locale(),
			]);
		}, 0);
	}
}
