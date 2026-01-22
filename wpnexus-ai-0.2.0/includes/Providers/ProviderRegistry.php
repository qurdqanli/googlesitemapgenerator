<?php
namespace WPNexusAI\Providers;

if (!defined('ABSPATH')) { exit; }

/**
 * Central registry for supported AI providers.
 *
 * Provider IDs are stored in DB (AI Keys) and referenced by settings.
 */
final class ProviderRegistry {

    /**
     * @return array<string, array{label:string, default_model:string, model_hint:string, base_url_key:string, model_key:string}>
     */
    public static function providers(): array {
        return [
            // Special:
            'auto' => [
                'label' => 'Auto (any active key)',
                'default_model' => '',
                'model_hint' => '',
                'base_url_key' => '',
                'model_key' => '',
            ],

            // Text providers:
            'openai' => [
                'label' => 'OpenAI',
                'default_model' => 'gpt-5',
                'model_hint' => 'gpt-5 / gpt-4.1 / gpt-4o / etc',
                'base_url_key' => 'openai_base_url',
                'model_key' => 'openai_model',
            ],
            'gemini' => [
                'label' => 'Google Gemini',
                'default_model' => 'gemini-2.5-flash',
                'model_hint' => 'gemini-2.5-flash / gemini-2.5-pro / etc',
                'base_url_key' => 'gemini_base_url',
                'model_key' => 'gemini_model',
            ],
            'claude' => [
                'label' => 'Claude (Anthropic)',
                'default_model' => 'claude-3-5-sonnet-latest',
                'model_hint' => 'claude-3-5-sonnet-latest / claude-3-5-haiku-latest',
                'base_url_key' => 'claude_base_url',
                'model_key' => 'claude_model',
            ],
            'mistral' => [
                'label' => 'Mistral',
                'default_model' => 'mistral-large-latest',
                'model_hint' => 'mistral-large-latest / mistral-medium-latest / etc',
                'base_url_key' => 'mistral_base_url',
                'model_key' => 'mistral_model',
            ],
            // OpenAI-compatible gateways (OpenRouter, local proxies, etc).
            'openai_compat' => [
                'label' => 'OpenAI-Compatible (Gateway)',
                'default_model' => 'gpt-4o-mini',
                'model_hint' => 'Any model your gateway supports',
                'base_url_key' => 'openai_compat_base_url',
                'model_key' => 'openai_compat_model',
            ],
        ];
    }

    public static function is_valid(string $provider): bool {
        $p = self::providers();
        return isset($p[$provider]);
    }

    /**
     * @return array<string,string> provider => label
     */
    public static function choices(bool $include_auto = true): array {
        $out = [];
        foreach (self::providers() as $id => $meta) {
            if (!$include_auto && $id === 'auto') {
                continue;
            }
            $out[$id] = $meta['label'];
        }
        return $out;
    }
}
