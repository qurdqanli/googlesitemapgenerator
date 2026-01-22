<?php
namespace WPNexusAI\Logging;

if (!defined('ABSPATH')) { exit; }

final class Logger {

    private static $instance;

    /** @var string */
    private $file;

    /** @var string */
    private $request_id;

    public static function instance(): self {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $upload_dir = wp_upload_dir(null, false);
        $dir = trailingslashit($upload_dir['basedir']) . 'wpnexus-ai/logs';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        $this->file = trailingslashit($dir) . 'core.log';
        $this->request_id = $this->make_request_id();
    }

    private function make_request_id(): string {
        try {
            return wp_generate_uuid4();
        } catch (\Throwable $e) {
            return substr(md5((string) microtime(true) . rand()), 0, 32);
        }
    }

    public function request_id(): string {
        return $this->request_id;
    }

    public function debug(string $event, array $context = []): void { $this->log('debug', $event, $context); }
    public function info(string $event, array $context = []): void { $this->log('info', $event, $context); }
    public function warn(string $event, array $context = []): void { $this->log('warn', $event, $context); }
    public function error(string $event, array $context = []): void { $this->log('error', $event, $context); }

    private function log(string $level, string $event, array $context): void {
        $row = [
            'ts' => gmdate('c'),
            'level' => $level,
            'event' => $event,
            'request_id' => $this->request_id,
            'context' => $context,
        ];
        $line = '[WPNexusAI] ' . wp_json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        // Avoid crashing the request if filesystem is read-only.
        try {
            error_log($line, 3, $this->file);
        } catch (\Throwable $e) {
            // fallback to default php error log
            error_log($line);
        }
    }
}
