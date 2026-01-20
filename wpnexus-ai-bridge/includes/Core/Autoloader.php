<?php
namespace WPNexusAIBridge\Core;

if (!defined('ABSPATH')) {
	exit;
}

final class Autoloader {
	/** @var array<string,string> */
	private static $prefixes = [];

	public static function register(string $prefix, string $base_dir): void {
		self::$prefixes[$prefix] = rtrim($base_dir, '/\\') . DIRECTORY_SEPARATOR;

		spl_autoload_register(function ($class) {
			self::autoload($class);
		});
	}

	private static function autoload(string $class): void {
		foreach (self::$prefixes as $prefix => $base_dir) {
			$len = strlen($prefix);
			if (strncmp($prefix, $class, $len) !== 0) {
				continue;
			}

			$relative = substr($class, $len);
			$relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
			$file     = $base_dir . $relative . '.php';

			if (is_readable($file)) {
				require_once $file;
			}
		}
	}
}
