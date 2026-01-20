<?php
namespace WPNexusAI\Engine\Extract;

use WPNexusAI\Logging\Logger;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Very lightweight segmenter for translation.
 *
 * For now it produces coarse segments (title/excerpt/content).
 * Later we can add block-aware segmentation.
 */
final class Segmenter {

	/** @var Logger */
	private $logger;

	public function __construct() {
		$this->logger = Logger::instance();
	}

	/**
	 * @param array<string,mixed> $extracted
	 * @return array<int,array<string,string>>
	 */
	public function segments(array $extracted): array {
		$post = isset($extracted['post']) && is_array($extracted['post']) ? $extracted['post'] : [];

		$title   = isset($post['title']) ? (string) $post['title'] : '';
		$excerpt = isset($post['excerpt']) ? (string) $post['excerpt'] : '';
		$content = isset($post['content']) ? (string) $post['content'] : '';

		$segments = [
			['key' => 'title', 'text' => $title],
			['key' => 'excerpt', 'text' => $excerpt],
			['key' => 'content', 'text' => $content],
		];

		/**
		 * Allow add/remove segments.
		 *
		 * @param array<int,array<string,string>> $segments
		 * @param array<string,mixed> $extracted
		 */
		$segments = apply_filters('wpnexus_ai_segmenter_segments', $segments, $extracted);

		$this->logger->debug('engine.segmenter.done', [
			'count' => is_array($segments) ? count($segments) : 0,
		]);

		return is_array($segments) ? $segments : [];
	}
}
