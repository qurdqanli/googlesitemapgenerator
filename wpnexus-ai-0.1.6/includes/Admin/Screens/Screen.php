<?php
namespace WPNexusAI\Admin\Screens;

use WPNexusAI\Logging\Logger;

if (!defined('ABSPATH')) {
	exit;
}

abstract class Screen {

	/** @var Logger */
	protected $logger;

	public function __construct() {
		$this->logger = Logger::instance();
	}

	abstract public function render(): void;

	protected function card_open(string $title): void {
		echo '<div class="wpnx-card">';
		echo '<h2>' . esc_html($title) . '</h2>';
	}

	protected function card_close(): void {
		echo '</div>';
	}

	protected function pill(string $text): string {
		return '<span class="wpnx-pill">' . esc_html($text) . '</span>';
	}

	protected function kv(string $k, string $v): void {
		echo '<div class="wpnx-kv"><strong>' . esc_html($k) . '</strong><span class="wpnx-muted">' . esc_html($v) . '</span></div>';
	}
}
