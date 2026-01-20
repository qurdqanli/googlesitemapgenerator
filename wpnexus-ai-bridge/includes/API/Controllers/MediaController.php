<?php
namespace WPNexusAIBridge\API\Controllers;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WPNexusAIBridge\Security\Auth;
use WPNexusAIBridge\Logging\Logger;
use WPNexusAIBridge\Domain\Services\MediaService;

if (!defined('ABSPATH')) {
	exit;
}

final class MediaController {

	private const NS = 'wpnexus-bridge/v1';

	private $logger;
	private $service;

	public function __construct() {
		$this->logger  = Logger::instance();
		$this->service = new MediaService();
	}

	public function register_routes(): void {
		register_rest_route(self::NS, '/media/upload', [
			'methods'             => 'POST',
			'callback'            => [$this, 'upload'],
			'permission_callback' => Auth::permission('upload_files'),
		]);

		register_rest_route(self::NS, '/media/attach-featured', [
			'methods'             => 'POST',
			'callback'            => [$this, 'attach_featured'],
			'permission_callback' => Auth::permission('edit_posts'),
		]);

		$this->logger->info('bridge.api.routes.media.registered', [
			'namespace' => self::NS,
			'routes'    => ['/media/upload', '/media/attach-featured'],
		]);
	}

	public function upload(WP_REST_Request $request) {
		$files = $request->get_file_params();
		if (empty($files['file'])) {
			return new WP_Error('wpnexus_bridge_upload_missing', t('rest_upload_missing'), ['status' => 400]);
		}

		$this->logger->info('bridge.api.media.upload.start', [
			'blog_id' => get_current_blog_id(),
			'uid'     => get_current_user_id(),
		]);

		$res = $this->service->upload($files['file']);
		if (is_wp_error($res)) {
			return $res;
		}

		return new WP_REST_Response($res, 200);
	}

	public function attach_featured(WP_REST_Request $request) {
		$payload = $request->get_json_params();
		if (!is_array($payload)) {
			return new WP_Error('wpnexus_bridge_invalid_params', t('rest_invalid_params'), ['status' => 400]);
		}

		$post_id = !empty($payload['post_id']) ? (int) $payload['post_id'] : 0;
		$att_id  = !empty($payload['attachment_id']) ? (int) $payload['attachment_id'] : 0;

		if ($post_id <= 0 || $att_id <= 0) {
			return new WP_Error('wpnexus_bridge_invalid_params', t('rest_invalid_params'), ['status' => 400]);
		}

		if (!current_user_can('edit_post', $post_id)) {
			return new WP_Error('wpnexus_bridge_forbidden', t('rest_forbidden'), ['status' => 403]);
		}

		$res = $this->service->attach_featured($post_id, $att_id);
		if (is_wp_error($res)) {
			return $res;
		}

		return new WP_REST_Response($res, 200);
	}
}
