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
			return new WP_Error('wpnexus_bridge_upload_failed', t('rest_upload_failed'), ['status' => 500]);
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

		$meta = wp_generate_attachment_metadata((int) $att_id, $file_path);
		if (is_array($meta)) {
			wp_update_attachment_metadata((int) $att_id, $meta);
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
			return new WP_Error('wpnexus_bridge_post_not_found', t('rest_post_not_found'), ['status' => 404]);
		}

		$a = get_post($attachment_id);
		if (!$a || $a->post_type !== 'attachment') {
			return new WP_Error('wpnexus_bridge_attachment_not_found', t('rest_attachment_not_found'), ['status' => 404]);
		}

		set_post_thumbnail($post_id, $attachment_id);

		return [
			'post_id'       => $post_id,
			'attachment_id' => $attachment_id,
			'attached'      => true,
		];
	}
}