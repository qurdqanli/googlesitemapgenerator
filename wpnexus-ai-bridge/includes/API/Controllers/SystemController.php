<?php
namespace WPNexusAIBridge\API\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WPNexusAIBridge\Logging\Logger;
use WPNexusAIBridge\Security\Auth;
use WPNexusAIBridge\Domain\Services\SiteService;
use WPNexusAIBridge\Domain\Services\LanguagesService;

if (!defined('ABSPATH')) {
	exit;
}

final class SystemController {

	private const NS = 'wpnexus-bridge/v1';

	private $logger;

	public function __construct() {
		$this->logger = Logger::instance();
	}

	public function register_routes(): void {
		register_rest_route(self::NS, '/health', [
			'methods'             => 'GET',
			'callback'            => [$this, 'health'],
			'permission_callback' => Auth::permission('manage_options'),
		]);

		register_rest_route(self::NS, '/site', [
			'methods'             => 'GET',
			'callback'            => [$this, 'site'],
			'permission_callback' => Auth::permission('manage_options'),
		]);

		register_rest_route(self::NS, '/languages', [
			'methods'             => 'GET',
			'callback'            => [$this, 'languages'],
			'permission_callback' => Auth::permission('manage_options'),
		]);

		$this->logger->info('bridge.api.routes.registered', [
			'namespace' => self::NS,
			'routes'    => ['/health', '/site', '/languages'],
		]);
	}

	public function health(WP_REST_Request $request) {
		$this->logger->info('bridge.api.health.start', [
			'blog_id' => get_current_blog_id(),
			'uid'     => get_current_user_id(),
		]);

		$data = [
			'product'    => 'WPNexus AI Bridge',
			'version'    => defined('WPNEXUS_AI_BRIDGE_VERSION') ? WPNEXUS_AI_BRIDGE_VERSION : 'dev',
			'multisite'  => (bool) is_multisite(),
			'integrations' => [
				'wpml'       => (bool) (defined('ICL_SITEPRESS_VERSION') || class_exists('SitePress')),
				'polylang'   => (bool) (defined('POLYLANG_VERSION') || function_exists('pll_current_language')),
				'woo'        => (bool) class_exists('WooCommerce'),
				'yoast'      => (bool) defined('WPSEO_VERSION'),
				'rankmath'   => (bool) defined('RANK_MATH_VERSION'),
				'seopress'   => (bool) (defined('SEOPRESS_VERSION') || defined('SEOPRESS_PRO_VERSION')),
			],
			'env' => [
				'wp'  => get_bloginfo('version'),
				'php' => PHP_VERSION,
			],
			'site' => [
				'blog_id'  => (int) get_current_blog_id(),
				'home_url' => home_url('/'),
				'site_url' => site_url('/'),
			],
			'auth' => [
				'supports'         => ['app_password', 'bridge_token'],
				'token_configured' => (bool) (is_string(get_option('wpnexus_ai_bridge_token_hash', '')) && get_option('wpnexus_ai_bridge_token_hash', '') !== ''),
			],
			'time' => [
				'utc' => gmdate('c'),
			],
		];

		$this->logger->info('bridge.api.health.done', [
			'multisite' => $data['multisite'],
		]);

		return new WP_REST_Response($data, 200);
	}

	public function site(WP_REST_Request $request) {
		$this->logger->info('bridge.api.site.start', [
			'blog_id' => get_current_blog_id(),
			'uid'     => get_current_user_id(),
		]);

		$service = new SiteService();
		$data    = $service->get_site_info();

		$this->logger->info('bridge.api.site.done', [
			'locale' => $data['locale'] ?? null,
		]);

		return new WP_REST_Response($data, 200);
	}

	public function languages(WP_REST_Request $request) {
		$this->logger->info('bridge.api.languages.start', [
			'blog_id' => get_current_blog_id(),
			'uid'     => get_current_user_id(),
		]);

		$svc = new LanguagesService();

		$data = [
			'provider'  => $svc->provider(),
			'languages' => $svc->languages(),
			'default'   => $svc->default_language(),
			'current'   => $svc->current_language(),
		];

		$this->logger->info('bridge.api.languages.done', [
			'provider' => $data['provider'],
			'count'    => is_array($data['languages']) ? count($data['languages']) : 0,
			'default'  => $data['default'],
			'current'  => $data['current'],
		]);

		return new WP_REST_Response($data, 200);
	}
}
