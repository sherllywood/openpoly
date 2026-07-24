<?php
/**
 * Engine error codes that the gateway may return.
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
	public const AUTH_INVALID         = 'AUTH_INVALID';
	public const AUTH_SITE_MISMATCH   = 'AUTH_SITE_MISMATCH';
	public const QUOTA_EXHAUSTED      = 'QUOTA_EXHAUSTED';
	public const RATE_LIMITED         = 'RATE_LIMITED';
	public const ENGINE_UNAVAILABLE   = 'ENGINE_UNAVAILABLE';
	public const INVALID_REQUEST      = 'INVALID_REQUEST';
	public const IDEMPOTENCY_CONFLICT = 'IDEMPOTENCY_CONFLICT';
	// phpcs:enable
}
