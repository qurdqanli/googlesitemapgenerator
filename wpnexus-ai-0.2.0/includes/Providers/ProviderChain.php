<?php
namespace WPNexusAI\Providers;

use WPNexusAI\Keys\KeysRepo;
use WPNexusAI\Logging\Logger;
use WPNexusAI\Settings\SettingsStore;

if (!defined('ABSPATH')) { exit; }

final class ProviderChain {

    /** @var Logger */
    private $logger;

    /** @var KeysRepo */
    private $keys;

    public function __construct() {
        $this->logger = Logger::instance();
        $this->keys = new KeysRepo();
    }

    /**
     * @return array{provider:string, key:string, model:string}|null
     */
    public function pick_key(): ?array {
        $provider = SettingsStore::str('text_provider', 'auto');
        $rows = $this->keys->all(true);
        $candidates = [];
        foreach ($rows as $row) {
            $p = (string) ($row['provider'] ?? '');
            if (!ProviderRegistry::is_valid($p) || $p === 'auto') {
                continue;
            }
            if ($provider !== 'auto' && $p !== $provider) {
                continue;
            }
            $key = $this->keys->key_plain($row);
            if ($key === '') {
                continue;
            }
            $candidates[] = [
                'provider' => $p,
                'key' => $key,
                'model' => (string) ($row['model'] ?? ''),
            ];
        }
        if (!$candidates) {
            $this->logger->warn('providers.no_active_keys', ['provider' => $provider]);
            return null;
        }

        /** @var array{provider:string,key:string,model:string} $picked */
        $picked = $candidates[array_rand($candidates)];
        return $picked;
    }

    /**
     * @param array<string, mixed> $req
     * @return array{ok:bool, text:string, error:string}
     */
    public function translate(array $req): array {
        $picked = $this->pick_key();
        if (!$picked) {
            return ['ok' => false, 'text' => '', 'error' => 'No active keys for provider'];
        }

        // Resolve model priority: request override > key model > settings default.
        $model = isset($req['model']) && is_string($req['model']) ? trim((string) $req['model']) : '';
        if ($model === '') {
            $model = trim((string) ($picked['model'] ?? ''));
        }
        if ($model === '') {
            $meta = ProviderRegistry::providers()[$picked['provider']] ?? null;
            if (is_array($meta) && !empty($meta['model_key'])) {
                $model = SettingsStore::str((string) $meta['model_key'], (string) ($meta['default_model'] ?? ''));
            }
        }
        if ($model !== '') {
            $req['model'] = $model;
        }

        if ($picked['provider'] === 'openai') {
            $adapter = new OpenAIAdapter($picked['key']);
            return $adapter->translate($req);
        }

        if ($picked['provider'] === 'gemini') {
            $adapter = new GeminiAdapter($picked['key']);
            return $adapter->translate($req);
        }

        if ($picked['provider'] === 'claude') {
            $adapter = new ClaudeAdapter($picked['key']);
            return $adapter->translate($req);
        }

        if ($picked['provider'] === 'mistral') {
            $adapter = new MistralAdapter($picked['key']);
            return $adapter->translate($req);
        }

        if ($picked['provider'] === 'openai_compat') {
            $adapter = new OpenAICompatAdapter($picked['key']);
            return $adapter->translate($req);
        }

        return ['ok' => false, 'text' => '', 'error' => 'Unknown provider'];
    }
}

