<?php
namespace WPNexusAIBridge\Domain\Services;

if (!defined('ABSPATH')) {
	exit;
}

final class LanguagesService {

	public function provider(): string {
		if ($this->is_wpml()) {
			return 'wpml';
		}
		if ($this->is_polylang()) {
			return 'polylang';
		}
		return 'wordpress';
	}

	public function languages(): array {
		if ($this->is_wpml()) {
			return $this->wpml_languages();
		}
		if ($this->is_polylang()) {
			return $this->polylang_languages();
		}
		return $this->wordpress_languages();
	}

	public function default_language(): ?string {
		if ($this->is_wpml()) {
			$def = apply_filters('wpml_default_language', null);
			return is_string($def) && $def !== '' ? $def : $this->fallback_code();
		}
		if ($this->is_polylang() && function_exists('pll_default_language')) {
			$def = pll_default_language();
			return is_string($def) && $def !== '' ? $def : $this->fallback_code();
		}
		return $this->fallback_code();
	}

	public function current_language(): ?string {
		if ($this->is_wpml()) {
			$cur = apply_filters('wpml_current_language', null);
			return is_string($cur) && $cur !== '' ? $cur : $this->default_language();
		}
		if ($this->is_polylang() && function_exists('pll_current_language')) {
			$cur = pll_current_language('slug');
			return is_string($cur) && $cur !== '' ? $cur : $this->default_language();
		}
		return $this->default_language();
	}

	private function is_wpml(): bool {
		return (bool) (defined('ICL_SITEPRESS_VERSION') || class_exists('SitePress') || has_filter('wpml_active_languages'));
	}

	private function is_polylang(): bool {
		return (bool) (defined('POLYLANG_VERSION') || function_exists('pll_languages_list'));
	}

	private function wpml_languages(): array {
		$list = apply_filters('wpml_active_languages', null, ['skip_missing' => 0]);
		if (!is_array($list)) {
			return $this->wordpress_languages();
		}

		$out = [];
		foreach ($list as $code => $row) {
			if (!is_array($row)) {
				continue;
			}
			$out[] = [
				'code'        => (string) $code,
				'locale'      => isset($row['default_locale']) ? (string) $row['default_locale'] : (isset($row['locale']) ? (string) $row['locale'] : null),
				'name'        => isset($row['translated_name']) ? (string) $row['translated_name'] : (isset($row['native_name']) ? (string) $row['native_name'] : (string) $code),
				'native_name' => isset($row['native_name']) ? (string) $row['native_name'] : null,
				'active'      => isset($row['active']) ? (bool) $row['active'] : null,
			];
		}

		return $out;
	}

	private function polylang_languages(): array {
		if (!function_exists('pll_languages_list')) {
			return $this->wordpress_languages();
		}

		$codes = pll_languages_list(['fields' => 'slug']);
		if (!is_array($codes) || empty($codes)) {
			return $this->wordpress_languages();
		}

		$out = [];
		foreach ($codes as $code) {
			$code = (string) $code;
			$locale = null;
			$name = $code;

			if (function_exists('pll_get_language')) {
				$lang = pll_get_language($code);
				if (is_object($lang)) {
					$locale = isset($lang->locale) ? (string) $lang->locale : null;
					$name   = isset($lang->name) ? (string) $lang->name : $code;
				} elseif (is_array($lang)) {
					$locale = isset($lang['locale']) ? (string) $lang['locale'] : null;
					$name   = isset($lang['name']) ? (string) $lang['name'] : $code;
				}
			}

			$out[] = [
				'code'        => $code,
				'locale'      => $locale,
				'name'        => $name,
				'native_name' => $name,
			];
		}

		return $out;
	}

	private function wordpress_languages(): array {
		$code = $this->fallback_code();
		return [[
			'code'        => $code,
			'locale'      => determine_locale(),
			'name'        => $code,
			'native_name' => null,
		]];
	}

	private function fallback_code(): string {
		$loc = determine_locale();
		$loc = is_string($loc) ? $loc : 'en_US';
		$code = strtolower(substr($loc, 0, 2));
		return $code !== '' ? $code : 'en';
	}
}
