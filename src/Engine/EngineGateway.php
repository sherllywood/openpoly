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
 * Interface that every translation engine adapter must satisfy.
 *
 * @since 1.0.0-dev
 */
interface EngineGateway {

	/**
	 * Translate a batch of segments.
	 *
	 * @param string                                   $source_language Source language code, e.g. "en_US".
	 * @param string                                   $target_language Target language code, e.g. "zh_CN".
	 * @param array<int, array{id:int, text:string}>   $segments        Segments to translate.
	 * @param string                                   $idempotency_key Idempotency key for safe retry.
	 * @return EngineResult
	 * @throws EngineException On gateway error.
	 */
	public function translate( string $source_language, string $target_language, array $segments, string $idempotency_key ): EngineResult;
}
