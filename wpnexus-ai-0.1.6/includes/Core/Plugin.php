<?php
namespace WPNexusAI\Core;

use WPNexusAI\I18n\I18n;
use WPNexusAI\Logging\Logger;
use WPNexusAI\DB\Installer;
use WPNexusAI\Admin\Admin;
use WPNexusAI\Queue\JobRunner;
use WPNexusAI\Providers\Bootstrap as ProvidersBootstrap;
use WPNexusAI\Queue\Tasks\Bootstrap as TasksBootstrap;
use WPNexusAI\Editor\Editor;
use WPNexusAI\Admin\Bulk\BulkActions;
use WPNexusAI\Engine\Sync\AutoEnqueue;



if (!defined('ABSPATH')) {
	exit;
}

final class Plugin {

	/** @var Logger */
	private $logger;

	/** @var I18n */
	private $i18n;

	/** @var Admin|null */
	private $admin;

	public function __construct() {
		$this->logger = Logger::instance();
		$this->i18n   = new I18n('wpnexus-ai', WPNEXUS_AI_DIR, WPNEXUS_AI_BASENAME);
	}

	public function init(): void {
		$this->logger->info('core.init.start', [
			'version' => defined('WPNEXUS_AI_VERSION') ? WPNEXUS_AI_VERSION : 'dev',
		]);

		$this->i18n->register();
 	    
  	     // Providers + tasks registration (TranslateTask etc.)
		ProvidersBootstrap::init();


		// Safe, lightweight upgrade check (no heavy work).
		Installer::maybe_upgrade();

		// Queue hooks (Action Scheduler + WP-Cron fallback).
		JobRunner::register();
 	
        // Register queue tasks (translate/upsert/etc).
		TasksBootstrap::init();

		// Auto enqueue lightweight sync jobs on post publish/update.
		AutoEnqueue::init();


		if (is_admin()) {
			$this->admin = new Admin();
			$this->admin->init();
		}
 	 	
        // Editor metabox + per-post overrides
		if (is_admin()) {
			Editor::init();
		}

        if (is_admin()) {
			BulkActions::init();
		}

		add_action('init', function () {
			$this->logger->debug('core.wp.init', [
				'is_admin' => is_admin(),
			]);
		}, 1);

		$this->logger->info('core.init.done');
	}

	public function logger(): Logger {
		return $this->logger;
	}
}

