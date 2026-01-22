<?php
namespace WPNexusAIBridge\API\Controllers;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WPNexusAIBridge\Security\Auth;
use WPNexusAIBridge\Logging\Logger;
use WPNexusAIBridge\Domain\Services\PostsService;

if (!defined('ABSPATH')) {
	exit;
}

final class PostsController {

	private const NS = 'wpnexus-bridge/v1';

	/** @var Logger */
	private $logger;

	/** @var PostsService */
	private $service;

	public function __construct() {
		$this->logger  = Logger::instance();
		$this->service = new PostsService();
	}

	public function register_routes(): void {
		register_rest_route(self::NS, '/posts/upsert', [
			'methods'             => 'POST',
			'callback'            => [$this, 'upsert'],
			'permission_callback' => Auth::permission('edit_posts'),
		]);

		register_rest_route(self::NS, '/posts/delete', [
			'methods'             => 'POST',
			'callback'            => [$this, 'delete'],
			'permission_callback' => Auth::permission('delete_posts'),
		]);

		$this->logger->info('bridge.api.routes.posts.registered', [
			'namespace' => self::NS,
			'routes'    => ['/posts/upsert', '/posts/delete'],
		]);
	}

	public function upsert(WP_REST_Request $request) {
		$payload = $request->get_json_params();
		if (!is_array($payload) || empty($payload)) {
			$payload = $request->get_body_params();
		}
		if (!is_array($payload) || empty($payload)) {
			return new WP_Error('wpnexus_bridge_invalid_params', t('rest_invalid_params'), ['status' => 400]);
		}
		$payload = $this->decode_b64_fields($payload);

		$this->logger->info('bridge.api.posts.upsert.start', [
			'blog_id' => get_current_blog_id(),
			'uid'     => get_current_user_id(),
			'post_type' => isset($payload['post_type']) ? (string) $payload['post_type'] : '',
			'has_signature' => !empty($payload['signature']),
			'has_remote_post_id' => !empty($payload['remote_post_id']),
		]);

		$res = $this->service->upsert($payload);
		if (is_wp_error($res)) {
			$this->logger->warning('bridge.api.posts.upsert.fail', [
				'code' => $res->get_error_code(),
				'msg'  => $res->get_error_message(),
			]);
			return $res;
		}

		$this->logger->info('bridge.api.posts.upsert.ok', [
			'post_id' => (int) $res['remote_post_id'],
			'action'  => (string) $res['action'],
		]);

		return new WP_REST_Response($res, 200);
	}

	public function delete(WP_REST_Request $request) {
		$payload = $request->get_json_params();
		if (!is_array($payload) || empty($payload)) {
			$payload = $request->get_body_params();
		}
		if (!is_array($payload) || empty($payload)) {
			return new WP_Error('wpnexus_bridge_invalid_params', t('rest_invalid_params'), ['status' => 400]);
		}

		$this->logger->info('bridge.api.posts.delete.start', [
			'blog_id' => get_current_blog_id(),
			'uid'     => get_current_user_id(),
			'has_signature' => !empty($payload['signature']),
			'has_remote_post_id' => !empty($payload['remote_post_id']),
		]);

		$res = $this->service->delete($payload);
		if (is_wp_error($res)) {
			$this->logger->warning('bridge.api.posts.delete.fail', [
				'code' => $res->get_error_code(),
				'msg'  => $res->get_error_message(),
			]);
			return $res;
		}

		$this->logger->info('bridge.api.posts.delete.ok', $res);

		return new WP_REST_Response($res, 200);
	}

	private function decode_b64_fields(array $payload): array {
		foreach (['title','content','excerpt'] as $k) {
			$bk = $k . '_b64';
			if (!empty($payload[$bk]) && is_string($payload[$bk]) && (empty($payload[$k]) || !is_string($payload[$k]))) {
				$decoded = base64_decode((string) $payload[$bk], true);
				if (is_string($decoded)) {
					$payload[$k] = $decoded;
				}
			}
			// If Core sends empty original and provides b64, decode anyway.
			if (!empty($payload[$bk]) && is_string($payload[$bk]) && isset($payload[$k]) && is_string($payload[$k]) && $payload[$k] === '') {
				$decoded = base64_decode((string) $payload[$bk], true);
				if (is_string($decoded)) {
					$payload[$k] = $decoded;
				}
			}
		}
		return $payload;
	}
}