<?php
namespace WPNexusAI\Admin\Screens;

use WPNexusAI\Settings\SettingsStore;
use WPNexusAI\Providers\ProviderRegistry;

if (!defined('ABSPATH')) { exit; }

final class SettingsScreen implements ScreenInterface {

    public function __construct() {
        add_action('admin_post_wpnexus_ai_save_settings', [$this, 'handle_save']);
    }

    public function render(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }

        echo '<div class="wrap"><h1>Settings</h1>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="wpnexus_ai_save_settings" />';
        wp_nonce_field('wpnexus_ai_save_settings');

        echo '<h2>Providers</h2>';
        echo '<table class="form-table"><tbody>';

        $text_provider = SettingsStore::str('text_provider', 'auto');
        echo '<tr><th><label>Text Provider</label></th><td><select name="text_provider">';
        foreach (ProviderRegistry::choices(true) as $k => $lab) {
            echo '<option value="' . esc_attr($k) . '" ' . selected($text_provider, $k, false) . '>' . esc_html($lab) . '</option>';
        }
        echo '</select><p class="description">Auto = any active key; or pin to one provider.</p></td></tr>';

        // OpenAI
        echo '<tr><th><label>OpenAI Base URL</label></th><td><input class="regular-text" name="openai_base_url" value="' . esc_attr(SettingsStore::str('openai_base_url', 'https://api.openai.com/v1')) . '"></td></tr>';
        echo '<tr><th><label>OpenAI Default Model</label></th><td><input class="regular-text" name="openai_model" value="' . esc_attr(SettingsStore::str('openai_model', 'gpt-5')) . '"></td></tr>';

        // Gemini
        echo '<tr><th><label>Gemini Base URL</label></th><td><input class="regular-text" name="gemini_base_url" value="' . esc_attr(SettingsStore::str('gemini_base_url', 'https://generativelanguage.googleapis.com/v1beta')) . '"></td></tr>';
        echo '<tr><th><label>Gemini Default Model</label></th><td><input class="regular-text" name="gemini_model" value="' . esc_attr(SettingsStore::str('gemini_model', 'gemini-2.5-flash')) . '"></td></tr>';

        // Claude
        echo '<tr><th><label>Claude Base URL</label></th><td><input class="regular-text" name="claude_base_url" value="' . esc_attr(SettingsStore::str('claude_base_url', 'https://api.anthropic.com/v1')) . '"></td></tr>';
        echo '<tr><th><label>Claude Default Model</label></th><td><input class="regular-text" name="claude_model" value="' . esc_attr(SettingsStore::str('claude_model', 'claude-3-5-sonnet-latest')) . '"></td></tr>';

        // Mistral
        echo '<tr><th><label>Mistral Base URL</label></th><td><input class="regular-text" name="mistral_base_url" value="' . esc_attr(SettingsStore::str('mistral_base_url', 'https://api.mistral.ai/v1')) . '"></td></tr>';
        echo '<tr><th><label>Mistral Default Model</label></th><td><input class="regular-text" name="mistral_model" value="' . esc_attr(SettingsStore::str('mistral_model', 'mistral-large-latest')) . '"></td></tr>';

        // OpenAI-Compatible gateway
        echo '<tr><th><label>OpenAI-Compatible Base URL</label></th><td><input class="regular-text" name="openai_compat_base_url" value="' . esc_attr(SettingsStore::str('openai_compat_base_url', '')) . '" placeholder="https://openrouter.ai/api/v1 (or your proxy)"><p class="description">Must be a gateway that supports /chat/completions.</p></td></tr>';
        echo '<tr><th><label>OpenAI-Compatible Default Model</label></th><td><input class="regular-text" name="openai_compat_model" value="' . esc_attr(SettingsStore::str('openai_compat_model', 'gpt-4o-mini')) . '"></td></tr>';

        echo '</tbody></table>';

        submit_button('Save Settings');

        echo '</form></div>';
    }

    public function handle_save(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
        check_admin_referer('wpnexus_ai_save_settings');

        $keys = [
            'text_provider',
            'openai_base_url','openai_model',
            'gemini_base_url','gemini_model',
            'claude_base_url','claude_model',
            'mistral_base_url','mistral_model',
            'openai_compat_base_url','openai_compat_model',
        ];

        foreach ($keys as $k) {
            $v = isset($_POST[$k]) ? sanitize_text_field((string) $_POST[$k]) : '';
            SettingsStore::set($k, $v);
        }

        wp_safe_redirect(admin_url('admin.php?page=wpnexus-ai-settings&saved=1'));
        exit;
    }
}

