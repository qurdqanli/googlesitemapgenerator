<?php
namespace WPNexusAIBridge\Admin;

use WPNexusAIBridge\Security\TokenManager;

if (!defined('ABSPATH')) { exit; }

final class Admin {

    /** @var TokenManager */
    private $tokens;

    public function __construct(TokenManager $tokens) {
        $this->tokens = $tokens;
    }

    public function menu(): void {
        add_options_page('WPNexus AI Bridge', 'WPNexus AI Bridge', 'manage_options', 'wpnexus-ai-bridge', [$this, 'render']);
    }

    public function render(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }

        $token = $this->tokens->get();
        $rotate = wp_nonce_url(admin_url('admin-post.php?action=wpnexus_ai_bridge_rotate_token'), 'wpnexus_ai_bridge_rotate_token');
        echo '<div class="wrap"><h1>WPNexus AI Bridge</h1>';

        echo '<p>This site is a <strong>receiver</strong>. Copy the token and paste it into the Core plugin Target.</p>';

        echo '<h2>Bridge Token</h2>';
        echo '<input class="large-text code" readonly value="' . esc_attr($token) . '" onclick="this.select();">';

        echo '<p><a class="button button-secondary" href="' . esc_url($rotate) . '">Rotate Token</a></p>';

        echo '<hr>';

        echo '<h2>Settings</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="wpnexus_ai_bridge_save_settings" />';
        wp_nonce_field('wpnexus_ai_bridge_save_settings');

        $log_level = get_option('wpnexus_ai_bridge_log_level', 'info');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label>Log Level</label></th><td><select name="log_level">';
        foreach (['debug','info','warn','error'] as $lvl) {
            echo '<option value="' . esc_attr($lvl) . '" ' . selected($log_level, $lvl, false) . '>' . esc_html($lvl) . '</option>';
        }
        echo '</select></td></tr>';
        echo '</tbody></table>';

        submit_button('Save Settings');
        echo '</form>';

        echo '</div>';
    }

    public function handle_rotate(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
        check_admin_referer('wpnexus_ai_bridge_rotate_token');

        $this->tokens->rotate();

        wp_safe_redirect(admin_url('options-general.php?page=wpnexus-ai-bridge&rotated=1'));
        exit;
    }

    public function handle_settings(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
        check_admin_referer('wpnexus_ai_bridge_save_settings');

        $lvl = sanitize_text_field((string) ($_POST['log_level'] ?? 'info'));
        update_option('wpnexus_ai_bridge_log_level', $lvl, true);

        wp_safe_redirect(admin_url('options-general.php?page=wpnexus-ai-bridge&saved=1'));
        exit;
    }
}
