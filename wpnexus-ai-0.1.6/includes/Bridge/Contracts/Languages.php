<?php
namespace WPNexusAI\Bridge\Contracts;

if (!defined('ABSPATH')) {
	exit;
}

final class Languages {

	/** @var array<int,array<string,mixed>> */
	public $languages = [];

	/** @var string|null */
	public $default;

	/** @var string|null */
	public $current;

	/**
	 * @param array<string,mixed> $data
	 */
	public static function from_array(array $data): Languages {
		$l = new self();

		if (isset($data['languages']) && is_array($data['languages'])) {
			$l->languages = array_values($data['languages']);
		} elseif (isset($data['langs']) && is_array($data['langs'])) {
			$l->languages = array_values($data['langs']);
		}

		$l->default = isset($data['default']) ? (string) $data['default'] : (isset($data['default_language']) ? (string) $data['default_language'] : null);
		$l->current = isset($data['current']) ? (string) $data['current'] : (isset($data['current_language']) ? (string) $data['current_language'] : null);

		return $l;
	}
}
