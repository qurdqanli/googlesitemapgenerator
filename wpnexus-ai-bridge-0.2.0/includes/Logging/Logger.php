<?php
namespace WPNexusAIBridge\Logging;

use WPNexusAIBridge\Settings\SettingsStore;

if (!defined('ABSPATH')) { exit; }

final class Logger {

    private static $inst;

    /** @var string */
    private $channel = 'WPNexusAIBridge';

    public static function instance(): self {
        if (!self::$inst) { self::$inst = new self(); }
        return self::$inst;
    }

    public function debug(string $event, array $context = []): void { $this->log('debug', $event, $context); }
    public function info(string $event, array $context = []): void { $this->log('info', $event, $context); }
    public function warn(string $event, array $context = []): void { $this->log('warn', $event, $context); }
    public function error(string $event, array $context = []): void { $this->log('error', $event, $context); }

    private function log(string $level, string $event, array $context): void {
        $allowed = ['debug'=>0,'info'=>1,'warn'=>2,'error'=>3];
        $min = get_option('wpnexus_ai_bridge_log_level', 'info');
        if (!isset($allowed[$min])) { $min = 'info'; }
        if ($allowed[$level] < $allowed[$min]) { return; }

        $payload = [
            'ts' => gmdate('c'),
            'level' => $level,
            'event' => $event,
            'context' => $context,
        ];

        error_log('[' . $this->channel . '] ' . wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
