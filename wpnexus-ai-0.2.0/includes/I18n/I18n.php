<?php
namespace WPNexusAI\I18n;

if (!defined('ABSPATH')) { exit; }

final class I18n {

    private static $instance;

    /** @var array<string, string> */
    private $strings = [];

    public static function instance(): self {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function load(): void {
        load_plugin_textdomain('wpnexus-ai', false, dirname(plugin_basename(WPNEXUS_AI_FILE)) . '/languages');

        // Canonical, code-driven strings (used by our t() helper).
        $this->strings = require WPNEXUS_AI_DIR . 'includes/I18n/languages/en.php';

        $locale = determine_locale();
        $candidate = WPNEXUS_AI_DIR . 'includes/I18n/languages/' . strtolower(str_replace('_', '-', $locale)) . '.php';
        if (is_readable($candidate)) {
            $override = require $candidate;
            if (is_array($override)) {
                $this->strings = array_merge($this->strings, $override);
            }
        }
    }

    /**
     * @param string $key
     * @param array<int, mixed> $args
     */
    public function t(string $key, array $args = []): string {
        $text = isset($this->strings[$key]) ? (string) $this->strings[$key] : $key;
        if ($args) {
            // vsprintf expects %s placeholders.
            try {
                $text = vsprintf($text, $args);
            } catch (\Throwable $e) {
                // ignore format errors
            }
        }
        return $text;
    }
}
