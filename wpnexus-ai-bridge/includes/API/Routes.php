<?php
namespace WPNexusAIBridge\API;

use WPNexusAIBridge\API\Controllers\SystemController;
use WPNexusAIBridge\API\Controllers\PostsController;
use WPNexusAIBridge\API\Controllers\TermsController;
use WPNexusAIBridge\API\Controllers\MediaController;
use WPNexusAIBridge\API\Controllers\LanguageController;

if (!defined('ABSPATH')) {
	exit;
}

final class Routes {

	public function init(): void {
		add_action('rest_api_init', [$this, 'register'], 5);
	}

	public function register(): void {
		$controllers = [
			new SystemController(),
			new PostsController(),
			new TermsController(),
			new MediaController(),
			new LanguageController(),
		];

		foreach ($controllers as $c) {
			$c->register_routes();
		}
	}
}

