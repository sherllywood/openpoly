<?php
/**
 * Translation engine contract.
 *
 * Every engine adapter must implement this interface so the plugin
 * can switch between the official OpenPoly gateway and custom
 * OpenAI-compatible endpoints.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Engine;

defined( 'ABSPATH' ) || exit;

/**
 * Engine translation result DTO.
 *
 * @since 1.0.0-dev
 */
final class EngineResult {

	/**
	 * Map of segment_id => translated_text.
	 *
	 * @var array<int, string>
	 */
	public array $translations;

	/**
	 * Billing / usage info from the gateway.
	 *
	 * @var array{chars_billed:int, tokens_in:int, tokens_out:int, tokens_cached:int}
	 */
	public array $usage;

	/**
	 * Whether the gateway response was from the idempotency cache.
	 *
	 * @var bool
	 */
	public bool $is_cached;

	/**
	 * Constructor.
	 *
	 * @param array<int, string>                                                     $translations Segment translations.
	 * @param array{chars_billed:int, tokens_in:int, tokens_out:int, tokens_cached:int} $usage        Usage info.
	 * @param bool                                                                   $is_cached    Whether cached.
	 */
	public function __construct( array $translations, array $usage = array(), bool $is_cached = false ) {
		$this->translations = $translations;
		$this->usage        = $usage;
		$this->is_cached    = $is_cached;
	}
}

/**
 * Error codes that the gateway may return.
 *
 * @since 1.0.0-dev
 */
final class EngineErrorCodes {
	public const AUTH_INVALID        = 'AUTH_INVALID';
	public const AUTH_SITE_MISMATCH  = 'AUTH_SITE_MISMATCH';
	public const QUOTA_EXHAUSTED      = 'QUOTA_EXHAUSTED';
	public const RATE_LIMITED         = 'RATE_LIMITED';
	public const ENGINE_UNAVAILABLE   = 'ENGINE_UNAVAILABLE';
	public const INVALID_REQUEST      = 'INVALID_REQUEST';
	public const IDEMPOTENCY_CONFLICT = 'IDEMPOTENCY_CONFLICT';
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
		$this->retry_after  = $retry_after;
	}
}

/**
 * Interface that every translation engine adapter must satisfy.
 *
 * @since 1.0.0-dev
 */
interface EngineGateway {

	/**
	 * Translate a batch of segments.
	 *
	 * @param string              $source_language Source language code, e.g. "en_US".
	 * @param string              $target_language Target language code, e.g. "zh_CN".
	 * @param array<int, array{id:int, text:string}> $segments   Segments to translate.
	 * @param string              $idempotency_key Idempotency key for safe retry.
	 * @return EngineResult
	 * @throws EngineException On gateway error.
	 */
	public function translate( string $source_language, string $target_language, array $segments, string $idempotency_key ): EngineResult;
}
