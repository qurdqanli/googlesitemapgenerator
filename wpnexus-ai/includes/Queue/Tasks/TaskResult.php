<?php
namespace WPNexusAI\Queue\Tasks;

if (!defined('ABSPATH')) {
	exit;
}

final class TaskResult {

	public const DONE        = 'done';
	public const RETRY       = 'retry';
	public const FAILED      = 'failed';
	public const NEEDS_INPUT = 'needs_input';

	/** @var string */
	public $status;

	/** @var int|null */
	public $next_run_ts;

	/** @var string|null */
	public $error;

	private function __construct(string $status, ?int $next_run_ts = null, ?string $error = null) {
		$this->status      = $status;
		$this->next_run_ts = $next_run_ts;
		$this->error       = $error;
	}

	public static function done(): self {
		return new self(self::DONE);
	}

	public static function retry(int $next_run_ts, string $error = ''): self {
		return new self(self::RETRY, $next_run_ts, $error !== '' ? $error : null);
	}

	public static function failed(string $error): self {
		return new self(self::FAILED, null, $error);
	}

	public static function needs_input(string $error): self {
		return new self(self::NEEDS_INPUT, null, $error);
	}
}
