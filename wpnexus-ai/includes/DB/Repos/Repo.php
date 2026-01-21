<?php
namespace WPNexusAI\DB\Repos;

use WPNexusAI\Logging\Logger;

if (!defined('ABSPATH')) {
	exit;
}

abstract class Repo {

	/** @var Logger */
	protected $logger;

	public function __construct() {
		$this->logger = Logger::instance();
	}
}
