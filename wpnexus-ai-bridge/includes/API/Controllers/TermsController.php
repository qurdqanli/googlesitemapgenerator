<?php
namespace WPNexusAIBridge\API\Controllers;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WPNexusAIBridge\Logging\Logger;
use WPNexusAIBridge\Security\Auth;
use WPNexusAIBridge\Domain\Services\TermsService;

if (!defined('ABSPATH')) {
	exit;
}

final class TermsController {

	private const NS = 'wpnexus-bridge/v1';

	/** @var Logger */
	private $logger;

	/** @var TermsService */
	private $service;

	public function __construct() {
		$this->logger  = Logger::instance();
		$this->service = new TermsService();
	}

	public function register_routes(): void {
		register_rest_route(self::NS, '/terms', [
			'methods'  => 'GET',
			'callback' => [$this, 'terms'],
			'permission_callback' => Auth::permission('manage_options'),
			'args' => [
				'taxonomy' => ['required' => true],
				'search'   => ['required' => false],
			],
		]);

		register_rest_route(self::NS, '/terms/upsert', [
			'methods'  => 'POST',
			'callback' => [$this, 'upsert'],
			'permission_callback' => Auth::permission('manage_options'),
		]);
	}

	public function terms(WP_REST_Request $request) {
		$taxonomy = (string) $request->get_param('taxonomy');
		$search   = (string) $request->get_param('search');

		$this->logger->info('bridge.api.terms.search.start', [
			'blog_id'    => get_current_blog_id(),
			'uid'        => get_current_user_id(),
			'taxonomy'   => $taxonomy,
			'has_search' => $search !== '',
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
			'blog_id'  => get_current_blog_id(),
			'uid'      => get_current_user_id(),
			'taxonomy' => isset($payload['taxonomy']) ? (string) $payload['taxonomy'] : '',
			'has_slug' => !empty($payload['slug']),
			'has_lang' => !empty($payload['language_code']),
		]);

		$res = $this->service->upsert($payload);
		if (is_wp_error($res)) {
			return $res;
		}

		$this->logger->info('bridge.api.terms.upsert.ok', [
			'term_id' => (int) ($res['term_id'] ?? 0),
			'action'  => (string) ($res['action'] ?? ''),
		]);

		return new WP_REST_Response($res, 200);
	}
}
