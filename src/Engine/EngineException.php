<?php
/**
 * Engine exception, thrown when the gateway returns an error.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Engine;

defined( 'ABSPATH' ) || exit;

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
