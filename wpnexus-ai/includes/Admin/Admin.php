<?php
namespace WPNexusAI\Admin;

use WPNexusAI\Logging\Logger;
use WPNexusAI\Admin\Screens\DashboardScreen;
use WPNexusAI\Admin\Screens\TargetsScreen;
use WPNexusAI\Admin\Screens\KeysScreen;
use WPNexusAI\Admin\Screens\JobsScreen;
use WPNexusAI\Admin\Screens\SettingsScreen;
use WPNexusAI\Admin\Actions\TargetsActions;
use WPNexusAI\Admin\Actions\KeysActions;


if (!defined('ABSPATH')) {
	exit;
}

final class Admin {

	public const CAPABILITY = 'manage_options';

	/** @var Logger */
	private $logger;

	/** @var array<string, object> */
	private $screens = [];

	public function __construct() {
		$this->logger = Logger::instance();

		$this->screens = [
			'wpnexus-ai'           => new DashboardScreen(),
			'wpnexus-ai-targets'   => new TargetsScreen(),
			'wpnexus-ai-keys'      => new KeysScreen(),
			'wpnexus-ai-jobs'      => new JobsScreen(),
			'wpnexus-ai-settings'  => new SettingsScreen(),
		];
	}

      	public function init(): void {
		$this->logger->info('admin.init.start');

		add_action('admin_menu', [$this, 'register_menu']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

		(new TargetsActions())->init();
 	    (new KeysActions())->init();

		$this->logger->info('admin.init.done');
	}


	public function register_menu(): void {
		$this->logger->info('admin.menu.register.start');

		$icon = 'dashicons-admin-site';

		add_menu_page(
			t('menu_wpnexus_ai'),
			t('menu_wpnexus_ai'),
			self::CAPABILITY,
			'wpnexus-ai',
			[$this, 'render_current_screen'],
			$icon,
			58
		);

		add_submenu_page(
			'wpnexus-ai',
			t('menu_dashboard'),
			t('menu_dashboard'),
			self::CAPABILITY,
			'wpnexus-ai',
			[$this, 'render_current_screen']
		);

		add_submenu_page(
			'wpnexus-ai',
			t('menu_targets'),
			t('menu_targets'),
			self::CAPABILITY,
			'wpnexus-ai-targets',
			[$this, 'render_current_screen']
		);

		add_submenu_page(
			'wpnexus-ai',
			t('menu_api_keys'),
			t('menu_api_keys'),
			self::CAPABILITY,
			'wpnexus-ai-keys',
			[$this, 'render_current_screen']
		);

		add_submenu_page(
			'wpnexus-ai',
			t('menu_jobs'),
			t('menu_jobs'),
			self::CAPABILITY,
			'wpnexus-ai-jobs',
			[$this, 'render_current_screen']
		);

		add_submenu_page(
			'wpnexus-ai',
			t('menu_settings'),
			t('menu_settings'),
			self::CAPABILITY,
			'wpnexus-ai-settings',
			[$this, 'render_current_screen']
		);

		$this->logger->info('admin.menu.register.done', [
			'screens' => array_keys($this->screens),
		]);
	}

	public function enqueue_assets(string $hook): void {
		$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
		if (strpos($page, 'wpnexus-ai') !== 0) {
			return;
		}

		$this->logger->debug('admin.assets.enqueue', [
			'hook' => $hook,
			'page' => $page,
		]);

		wp_register_style('wpnexus-ai-admin', false, [], WPNEXUS_AI_VERSION);
		wp_enqueue_style('wpnexus-ai-admin');

		$css = "
		.wpnx-wrap .wpnx-grid{display:grid;grid-template-columns:1fr;gap:16px;margin-top:16px}
		@media(min-width:1100px){.wpnx-wrap .wpnx-grid{grid-template-columns:2fr 1fr}}
		.wpnx-card{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:16px}
		.wpnx-card h2{margin-top:0}
		.wpnx-muted{color:#646970}
		.wpnx-kv{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid #f0f0f1}
		.wpnx-kv:last-child{border-bottom:none}
		.wpnx-pill{display:inline-block;padding:2px 10px;border-radius:999px;border:1px solid #dcdcde;background:#f6f7f7}
		.wpnx-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
		";
		wp_add_inline_style('wpnexus-ai-admin', $css);
	}

	public function render_current_screen(): void {
		if (!current_user_can(self::CAPABILITY)) {
			wp_die(esc_html__('Sorry, you are not allowed to access this page.'));
		}

		$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : 'wpnexus-ai';
		$screen = $this->screens[$page] ?? $this->screens['wpnexus-ai'];

		$this->logger->info('admin.screen.render', [
			'page' => $page,
			'screen' => is_object($screen) ? get_class($screen) : null,
		]);

		echo '<div class="wrap wpnx-wrap">';
		echo '<h1>' . esc_html(t('menu_wpnexus_ai')) . '</h1>';

		if (method_exists($screen, 'render')) {
			$screen->render();
		} else {
			echo '<p>' . esc_html(t('admin_screen_missing')) . '</p>';
		}

		echo '</div>';
	}

	public static function url(string $page, array $args = []): string {
		$args = array_merge(['page' => $page], $args);
		return add_query_arg(array_map('rawurlencode', $args), admin_url('admin.php'));
	}
}
