<?php
/**
 * Test: OpenAiCompatibleEngine request building and error handling.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Engine;

use OpenPoly\Engine\EngineException;
use OpenPoly\Engine\EngineResult;
use OpenPoly\Engine\OpenAiCompatibleEngine;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Engine\OpenAiCompatibleEngine
 */
final class OpenAiCompatibleEngineTest extends TestCase {

	public function testTranslateEmptySegmentsReturnsEmptyResult(): void {
		$engine = new OpenAiCompatibleEngine( 'https://example.com/', 'key123' );
		$result = $engine->translate( 'en', 'zh', array(), 'ik:1' );

		self::assertCount( 0, $result->translations );
	}

	public function testIdempotencyKeyIsDeterministic(): void {
		$texts  = array( 1 => 'Hello', 2 => 'World' );
		$key1   = OpenAiCompatibleEngine::idempotency_key( 42, $texts );
		$key2   = OpenAiCompatibleEngine::idempotency_key( 42, $texts );

		self::assertSame( $key1, $key2 );
	}

	public function testIdempotencyKeyChangesWithDifferentText(): void {
		$texts1 = array( 1 => 'Hello' );
		$texts2 = array( 1 => 'World' );
		$key1   = OpenAiCompatibleEngine::idempotency_key( 42, $texts1 );
		$key2   = OpenAiCompatibleEngine::idempotency_key( 42, $texts2 );

		self::assertNotSame( $key1, $key2 );
	}

	public function testIdempotencyKeyChangesWithDifferentTrid(): void {
		$texts = array( 1 => 'Hello' );
		$key1  = OpenAiCompatibleEngine::idempotency_key( 1, $texts );
		$key2  = OpenAiCompatibleEngine::idempotency_key( 2, $texts );

		self::assertNotSame( $key1, $key2 );
	}

	public function testEngineResultConstructor(): void {
		$result = new EngineResult(
			array( 1 => 'Hola', 2 => 'Mundo' ),
			array(
				'chars_billed'  => 10,
				'tokens_in'     => 20,
				'tokens_out'    => 30,
				'tokens_cached' => 5,
			)
		);

		self::assertSame( 'Hola', $result->translations[1] );
		self::assertSame( 10, $result->usage['chars_billed'] );
		self::assertFalse( $result->is_cached );
	}

	public function testEngineExceptionCarriesErrorCode(): void {
		$ex = new EngineException( 'Quota exhausted', 'QUOTA_EXHAUSTED', 0 );

		self::assertSame( 'QUOTA_EXHAUSTED', $ex->error_code );
		self::assertSame( 'Quota exhausted', $ex->getMessage() );
	}

	public function testEngineExceptionCarriesRetryAfter(): void {
		$ex = new EngineException( 'Rate limited', 'RATE_LIMITED', 30 );

		self::assertSame( 30, $ex->retry_after );
	}
}
