<?php
namespace WPNexusAI\Licensing;

use WPNexusAI\Logging\Logger;
use WPNexusAI\DB\Repos\LicenseRepo;

if (!defined('ABSPATH')) {
	exit;
}

final class LicensingService {

	/** @var Logger */
	private $logger;

	/** @var LicenseRepo */
	private $repo;

	public function __construct() {
		$this->logger = Logger::instance();
		$this->repo   = new LicenseRepo();
	}

	public function opt_in_enabled(): bool {
		$row = $this->repo->get_row();
		return !empty($row['opt_in']);
	}

	public function entitlements(): Entitlements {
		$row = $this->repo->get_row();

		// If not opted in, keep full testing mode.
		if (empty($row['opt_in'])) {
			return Entitlements::default_unlicensed();
		}

		return Entitlements::from_json($row['entitlements_json'] ?? '{}');
	}

	/**
	 * Hard enforcement switch (default OFF for testing).
	 * Later you can flip this by filter or setting.
	 */
	public function enforcement_enabled(): bool {
		return (bool) apply_filters('wpnexus_ai_license_enforce', false);
	}

	public function targets_limit(): int {
		$e = $this->entitlements();
		return max(0, (int) $e->targets_limit);
	}

	public function can_add_target(int $current_targets_count): bool {
		if (!$this->enforcement_enabled()) {
			return true;
		}

		$limit = $this->targets_limit();
		if ($limit <= 0) {
			return true;
		}

		return $current_targets_count < $limit;
	}
}

