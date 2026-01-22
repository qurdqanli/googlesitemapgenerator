<?php
namespace WPNexusAI\Core;

if (!defined('ABSPATH')) { exit; }

final class Autoloader {

    /** @var bool */
    private static $registered = false;

    public static function register(): void {
        if (self::$registered) { return; }
        self::$registered = true;

        spl_autoload_register(function ($class) {
            if (!is_string($class) || strpos($class, 'WPNexusAI\\') !== 0) {
                return;
            }
            $relative = substr($class, strlen('WPNexusAI\\'));
            $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
            $file = WPNEXUS_AI_DIR . 'includes' . DIRECTORY_SEPARATOR . $relative . '.php';
            if (is_readable($file)) {
                require_once $file;
            }
        });
    }
}
