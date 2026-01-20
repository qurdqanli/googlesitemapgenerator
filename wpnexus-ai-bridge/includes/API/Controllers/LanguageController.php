<?php
namespace WPNexusAIBridge\API\Controllers;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WPNexusAIBridge\Security\Auth;
use WPNexusAIBridge\Logging\Logger;
use WPNexusAIBridge\Domain\Services\LanguageAssignmentService;

if (!defined('ABSPATH')) {
	exit;
}

final class LanguageController {

	private const NS = 'wpnexus-bridge/v1';

	private $logger;
	private $service;

	public function __construct() {
		$this->logger  = Logger::instance();
		$this->service = new LanguageAssignmentService();
	}

	public function register_routes(): void {
		register_rest_route(self::NS, '/language/assign', [
			'methods'             => 'POST',
			'callback'            => [$this, 'assign'],
			'permission_callback' => Auth::permission('edit_posts'),
		]);

		$this->logger->info('bridge.api.routes.language.registered', [
			'namespace' => self::NS,
			'route'     => '/language/assign',
		]);
	}

	public function assign(WP_REST_Request $request) {
		$payload = $request->get_json_params();
		if (!is_array($payload)) {
			return new WP_Error('wpnexus_bridge_invalid_params', t('rest_invalid_params'), ['status' => 400]);
		}

		$post_id = !empty($payload['remote_post_id']) ? (int) $payload['remote_post_id'] : 0;
		$lang    = !empty($payload['language_code']) ? (string) $payload['language_code'] : '';

		if ($post_id <= 0 || trim($lang) === '') {
			return new WP_Error('wpnexus_bridge_invalid_params', t('rest_invalid_params'), ['status' => 400]);
		}

		if (!current_user_can('edit_post', $post_id)) {
			return new WP_Error('wpnexus_bridge_forbidden', t('rest_forbidden'), ['status' => 403]);
		}

		$res = $this->service->assign_post_language($post_id, $lang);
		if (is_wp_error($res)) {
			return $res;
		}

		return new WP_REST_Response([
			'remote_post_id' => $post_id,
			'language_code'  => $lang,
			'assigned'       => true,
		], 200);
	}
}
