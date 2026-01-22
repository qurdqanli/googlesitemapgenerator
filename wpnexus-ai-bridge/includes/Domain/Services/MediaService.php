<?php
namespace WPNexusAIBridge\Domain\Services;

use WP_Error;
use WPNexusAIBridge\Logging\Logger;

if (!defined('ABSPATH')) {
	exit;
}

final class MediaService {

	private $logger;

	public function __construct() {
		$this->logger = Logger::instance();
	}

	public function upload(array $file) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$overrides = ['test_form' => false];

		$handled = wp_handle_upload($file, $overrides);
		if (!is_array($handled) || empty($handled['file'])) {
			return new WP_Error('wpnexus_bridge_upload_failed', 'Rest upload failed', ['status' => 500]);
		}

		$file_path = (string) $handled['file'];
		$file_url  = isset($handled['url']) ? (string) $handled['url'] : '';
		$type      = isset($handled['type']) ? (string) $handled['type'] : 'application/octet-stream';

		$attachment = [
			'post_mime_type' => $type,
			'post_title'     => sanitize_text_field(pathinfo($file_path, PATHINFO_FILENAME)),
			'post_content'   => '',
			'post_status'    => 'inherit',
		];

		$att_id = wp_insert_attachment($attachment, $file_path);
		if (is_wp_error($att_id)) {
			return $att_id;
		}

		// Hardening: avoid fatal errors on large images or low-memory environments
		$size = @filesize($file_path);
		$size = is_int($size) ? $size : 0;

		$this->logger->info('bridge.media.upload.attachment.created', [
			'attachment_id' => (int) $att_id,
			'file_size'     => $size,
			'mime'          => $type,
		]);

		$should_generate = true;
		// Skip very large files by default (8MB threshold)
		if ($size > 8 * 1024 * 1024) {
			$should_generate = false;
		}

		$should_generate = (bool) apply_filters('wpnexus_ai_bridge_generate_attachment_metadata', $should_generate, (int) $att_id, $file_path, $type, $size);

		if ($should_generate) {
			if (function_exists('wp_raise_memory_limit')) {
				wp_raise_memory_limit('image');
			}
			$this->logger->info('bridge.media.metadata.start', ['attachment_id' => (int) $att_id]);

			try {
				$meta = wp_generate_attachment_metadata((int) $att_id, $file_path);
				if (is_array($meta)) {
					wp_update_attachment_metadata((int) $att_id, $meta);
					$this->logger->info('bridge.media.metadata.done', ['attachment_id' => (int) $att_id]);
				} else {
					$this->logger->warning('bridge.media.metadata.skip_or_fail', ['attachment_id' => (int) $att_id]);
				}
			} catch (\Throwable $e) {
				$this->logger->error('bridge.media.metadata.throwable', [
					'attachment_id' => (int) $att_id,
					'msg'           => $e->getMessage(),
				]);
			}
		} else {
			$this->logger->warning('bridge.media.metadata.skipped.large_file', [
				'attachment_id' => (int) $att_id,
				'file_size'     => $size,
			]);
		}

		$this->logger->info('bridge.media.upload.ok', [
			'attachment_id' => (int) $att_id,
		]);

		return [
			'attachment_id' => (int) $att_id,
			'url'           => $file_url,
		];
	}

	public function attach_featured(int $post_id, int $attachment_id) {
		$p = get_post($post_id);
		if (!$p) {
			return new WP_Error('wpnexus_bridge_post_not_found', 'Rest post not found', ['status' => 404]);
		}

		$a = get_post($attachment_id);
		if (!$a || $a->post_type !== 'attachment') {
			return new WP_Error('wpnexus_bridge_attachment_not_found', 'Rest attachment not found', ['status' => 404]);
		}

		set_post_thumbnail($post_id, $attachment_id);

		return [
			'post_id'       => $post_id,
			'attachment_id' => $attachment_id,
			'attached'      => true,
		];
	}
}