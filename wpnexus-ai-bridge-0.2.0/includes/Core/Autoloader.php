<?php
namespace WPNexusAIBridge\Core;

if (!defined('ABSPATH')) { exit; }

final class Autoloader {

    public static function register(): void {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    public static function autoload(string $class): void {
        if (strpos($class, 'WPNexusAIBridge\\') !== 0) { return; }

        $rel = substr($class, strlen('WPNexusAIBridge\\'));
        $rel = str_replace('\\', '/', $rel);
        $file = WPNEXUS_AI_BRIDGE_PATH . 'includes/' . $rel . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
}
