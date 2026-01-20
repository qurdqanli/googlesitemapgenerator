<?php
namespace WPNexusAI\Editor;

use WPNexusAI\Logging\Logger;
use WPNexusAI\DB\Repos\TargetsRepo;
use WPNexusAI\Engine\Routing\LanguageRouter;
use WPNexusAI\Util\Lang;

if (!defined('ABSPATH')) {
	exit;
}

final class Editor {

	private const NONCE_ACTION = 'wpnexus_ai_metabox_save';
	private const NONCE_NAME   = '_wpnexus_ai_nonce';

	/** @var Logger */
	private $logger;

	/** @var TargetsRepo */
	private $targets;

	public function __construct() {
		$this->logger  = Logger::instance();
		$this->targets = new TargetsRepo();
	}

	public static function init(): void {
		$inst = new self();

		add_action('add_meta_boxes', [$inst, 'register_metabox'], 20);
		add_action('save_post', [$inst, 'save_metabox'], 20, 2);

		$inst->logger->info('editor.init.done');
	}

	public function register_metabox(): void {
		$post_types = apply_filters('wpnexus_ai_editor_post_types', ['post', 'page']);
		if (!is_array($post_types)) {
			$post_types = ['post', 'page'];
		}

		foreach ($post_types as $pt) {
			$pt = sanitize_key((string) $pt);
			if ($pt === '') {
				continue;
			}

			add_meta_box(
				'wpnexus-ai',
				t('editor_metabox_title'),
				[$this, 'render_metabox'],
				$pt,
				'side',
				'default'
			);
		}

		$this->logger->debug('editor.metabox.registered', [
			'post_types' => $post_types,
		]);
	}

	public function render_metabox(\WP_Post $post): void {
		if (!current_user_can('edit_post', $post->ID)) {
			echo '<p>' . esc_html(t('editor_no_access')) . '</p>';
			return;
		}

		wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

		$meta = get_post_meta($post->ID, LanguageRouter::META_KEY, true);
		$meta = is_array($meta) ? $meta : [];

		$send_original = !empty($meta['send_original']);
		$source_lang   = !empty($meta['source_lang']) ? (string) $meta['source_lang'] : Lang::from_locale((string) get_locale());

		$targets_overrides = (!empty($meta['targets']) && is_array($meta['targets'])) ? $meta['targets'] : [];

		echo '<p><label style="display:block;margin-bottom:6px;">';
		echo '<input type="checkbox" name="wpnexus_ai[send_original]" value="1" ' . checked($send_original, true, false) . ' /> ';
		echo esc_html(t('editor_send_original'));
		echo '</label></p>';

		echo '<p class="description" style="margin-top:-4px;margin-bottom:10px;">' . esc_html(t('editor_send_original_help')) . '</p>';

		echo '<p><label style="display:block;margin-bottom:6px;">';
		echo esc_html(t('editor_source_lang')) . '<br />';
		echo '<input type="text" class="widefat" name="wpnexus_ai[source_lang]" value="' . esc_attr((string) $source_lang) . '" placeholder="auto" />';
		echo '</label></p>';

		echo '<p class="description" style="margin-top:-4px;margin-bottom:10px;">' . esc_html(t('editor_source_lang_help')) . '</p>';

		$targets = $this->targets->list(200);

		if (empty($targets)) {
			echo '<p class="description">' . esc_html(t('editor_no_targets')) . '</p>';
			return;
		}

		echo '<div style="margin-top:10px;border-top:1px solid #eee;padding-top:10px;">';
		echo '<strong>' . esc_html(t('editor_target_overrides')) . '</strong>';
		echo '<p class="description" style="margin-top:4px;">' . esc_html(t('editor_target_overrides_help')) . '</p>';

		foreach ($targets as $tr) {
			$tid = (int) ($tr['id'] ?? 0);
			if ($tid <= 0) {
				continue;
			}

			$name = !empty($tr['base_url']) ? (string) $tr['base_url'] : ('Target #' . $tid);

			$ov = isset($targets_overrides[$tid]) && is_array($targets_overrides[$tid]) ? $targets_overrides[$tid] : [];
			$lang = !empty($ov['language']) ? (string) $ov['language'] : 'auto';
			$so   = !empty($ov['send_original']);

			echo '<div style="margin:10px 0;padding:8px;border:1px solid #eee;border-radius:6px;">';
			echo '<div style="font-weight:600;margin-bottom:6px;">' . esc_html($name) . '</div>';

			echo '<label style="display:block;margin-bottom:6px;">';
			echo esc_html(t('editor_target_language')) . '<br />';
			echo '<input type="text" class="widefat" name="wpnexus_ai[targets][' . esc_attr((string) $tid) . '][language]" value="' . esc_attr((string) $lang) . '" placeholder="auto" />';
			echo '</label>';

			echo '<label style="display:block;margin-top:6px;">';
			echo '<input type="checkbox" name="wpnexus_ai[targets][' . esc_attr((string) $tid) . '][send_original]" value="1" ' . checked($so, true, false) . ' /> ';
			echo esc_html(t('editor_target_send_original'));
			echo '</label>';

			echo '</div>';
		}

		echo '</div>';
	}

	public function save_metabox(int $post_id, \WP_Post $post): void {
		// Autosave / revision guards
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (wp_is_post_revision($post_id)) {
			return;
		}
		if (!current_user_can('edit_post', $post_id)) {
			return;
		}

		$nonce = isset($_POST[self::NONCE_NAME]) ? (string) $_POST[self::NONCE_NAME] : '';
		if ($nonce === '' || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
			return;
		}

		$data = isset($_POST['wpnexus_ai']) && is_array($_POST['wpnexus_ai']) ? (array) $_POST['wpnexus_ai'] : [];

		$send_original = !empty($data['send_original']);

		$source_lang = isset($data['source_lang']) ? (string) $data['source_lang'] : '';
		$source_lang = Lang::sanitize_code($source_lang);
		if ($source_lang === '') {
			$source_lang = 'auto';
		}

		$targets = [];
		if (!empty($data['targets']) && is_array($data['targets'])) {
			foreach ($data['targets'] as $tid => $ov) {
				$tid = (int) $tid;
				if ($tid <= 0 || !is_array($ov)) {
					continue;
				}

				$lang = isset($ov['language']) ? (string) $ov['language'] : '';
				$lang = Lang::sanitize_code($lang);
				if ($lang === '') {
					$lang = 'auto';
				}

				$so = !empty($ov['send_original']);

				$targets[$tid] = [
					'language'      => $lang,
					'send_original' => $so ? 1 : 0,
				];
			}
		}

		$overrides = [
			'send_original' => $send_original ? 1 : 0,
			'source_lang'   => $source_lang,
			'targets'       => $targets,
		];

		update_post_meta($post_id, LanguageRouter::META_KEY, $overrides);

		$this->logger->info('editor.metabox.saved', [
			'post_id'       => $post_id,
			'send_original' => $send_original ? 1 : 0,
			'source_lang'   => $source_lang,
			'targets'       => count($targets),
		]);
	}
}
