<?php
namespace WPNexusAI\Engine\Sync;

if (!defined('ABSPATH')) {
	exit;
}

final class TranslationApplier {

	/**
	 * @param array<string,mixed> $extracted
	 * @param array<string,string> $translations
	 * @return array<string,mixed>
	 */
	public function apply(array $extracted, array $translations): array {
		if (empty($extracted['post']) || !is_array($extracted['post'])) {
			return $extracted;
		}

		$post = $extracted['post'];

		if (isset($translations['title']) && $translations['title'] !== '') {
			$post['title'] = (string) $translations['title'];
		}
		if (isset($translations['excerpt']) && $translations['excerpt'] !== '') {
			$post['excerpt'] = (string) $translations['excerpt'];
		}
		if (isset($translations['content']) && $translations['content'] !== '') {
			$post['content'] = (string) $translations['content'];
		}

		$extracted['post'] = $post;
		return $extracted;
	}
}
