<?php
/**
 * Engine error codes and exception.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Engine;

defined( 'ABSPATH' ) || exit;

/**
 * Error codes that the gateway may return.
 *
 * @since 1.0.0-dev
 */
final class EngineErrorCodes {
	// phpcs:disable Generic.NamingConventions.UpperCaseConstantName
	public const AUTH_INVALID        = 'AUTH_INVALID';
	public const AUTH_SITE_MISMATCH  = 'AUTH_SITE_MISMATCH';
	public const QUOTA_EXHAUSTED     = 'QUOTA_EXHAUSTED';
	public const RATE_LIMITED        = 'RATE_LIMITED';
	public const ENGINE_UNAVAILABLE  = 'ENGINE_UNAVAILABLE';
	public const INVALID_REQUEST     = 'INVALID_REQUEST';
	public const IDEMPOTENCY_CONFLICT = 'IDEMPOTENCY_CONFLICT';
	// phpcs:enable
}

/**
 * Engine exception, thrown when the gateway returns an error.
 *
 * @since 1.0.0-dev
 */
final class EngineException extends \RuntimeException {

	/**
	 * Gateway error code from EngineErrorCodes.
	 *
	 * @var string
	 */
	public string $error_code;

	/**
	 * Retry-after seconds, if receiving 429.
	 *
	 * @var int
	 */
	public int $retry_after;

	/**
	 * Constructor.
	 *
	 * @param string $message     Human-readable message.
	 * @param string $error_code  Gateway error code.
	 * @param int    $retry_after Retry-after seconds.
	 */
	public function __construct( string $message, string $error_code = '', int $retry_after = 0 ) {
		parent::__construct( $message );
		$this->error_code  = $error_code;
		$this->retry_after = $retry_after;
	}
}
