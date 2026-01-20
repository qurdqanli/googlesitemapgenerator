<?php
namespace WPNexusAIBridge\API\Controllers;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WPNexusAIBridge\Security\Auth;
use WPNexusAIBridge\Logging\Logger;
use WPNexusAIBridge\Domain\Services\TermsService;

if (!defined('ABSPATH')) {
	exit;
}

final class TermsController {

	private const NS = 'wpnexus-bridge/v1';

	private $logger;
	private $service;

	public function __construct() {
		$this->logger  = Logger::instance();
		this->service  = new TermsService();
	}

	public function register_routes(): void {
		register_rest_route(self::NS, '/terms', [
			'methods'             => 'GET',
			'callback'            => [$this, 'search'],
			'permission_callback' => Auth::permission('manage_categories'),
		]);

		register_rest_route(self::NS, '/terms/upsert', [
			'methods'             => 'POST',
			'callback'            => [$this, 'upsert'],
			'permission_callback' => Auth::permission('manage_categories'),
		]);

		$this->logger->info('bridge.api.routes.terms.registered', [
			'namespace' => self::NS,
			'routes'    => ['/terms', '/terms/upsert'],
		]);
	}

	public function search(WP_REST_Request $request) {
		$taxonomy = sanitize_key((string) $request->get_param('taxonomy'));
		$search   = (string) $request->get_param('search');

		if ($taxonomy === '' || !taxonomy_exists($taxonomy)) {
			return new WP_Error('wpnexus_bridge_taxonomy_invalid', t('rest_taxonomy_invalid'), ['status' => 400]);
		}

		$this->logger->info('bridge.api.terms.search', [
			'blog_id'  => get_current_blog_id(),
			'uid'      => get_current_user_id(),
			'taxonomy' => $taxonomy,
			'has_search' => trim($search) !== '',
		]);

		$res = $this->service->search($taxonomy, $search);
		if (is_wp_error($res)) {
			return $res;
		}

		return new WP_REST_Response(['terms' => $res], 200);
	}

	public function upsert(WP_REST_Request $request) {
		$payload = $request->get_json_params();
		if (!is_array($payload)) {
			return new WP_Error('wpnexus_bridge_invalid_params', t('rest_invalid_params'), ['status' => 400]);
		}

		$this->logger->info('bridge.api.terms.upsert.start', [
			'blog_id' => get_current_blog_id(),
			'uid'     => get_current_user_id(),
			'taxonomy' => isset($payload['taxonomy']) ? (string) $payload['taxonomy'] : '',
		]);

		$res = $this->service->upsert($payload);
		if (is_wp_error($res)) {
			return $res;
		}

		return new WP_REST_Response($res, 200);
	}
}
