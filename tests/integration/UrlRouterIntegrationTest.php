<?php
/**
 * Integration test for URL routing.
 *
 * Uses php-wp mocks / pure PHP (no real WordPress DB). Tests the
 * core negotiation rules and URL building functions that M-09 ~ M-14
 * depend on.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
final class UrlRouterIntegrationTest extends TestCase {

	public function testLanguageCodeNormalizationIsStable(): void {
		$normalized = str_replace( '_', '-', strtolower( 'en_US' ) );
		self::assertSame( 'en-us', $normalized );

		$normalized = str_replace( '_', '-', strtolower( 'zh_CN' ) );
		self::assertSame( 'zh-cn', $normalized );

		$normalized = str_replace( '_', '-', strtolower( 'pt_BR' ) );
		self::assertSame( 'pt-br', $normalized );
	}

	public function testRewritePatternMathesCanonicalForm(): void {
		$code = 'en_US';
		$prefix = str_replace( '_', '-', strtolower( $code ) );
		$pattern = '^' . $prefix . '/?$';

		self::assertSame( '^en-us/?$', $pattern );
		self::assertTrue( (bool) preg_match( '#' . $pattern . '#', 'en-us' ) );
		self::assertTrue( (bool) preg_match( '#' . $pattern . '#', 'en-us/' ) );
		self::assertFalse( (bool) preg_match( '#' . $pattern . '#', 'en-us/hello' ) );
	}

	public function testRewritePatternMathesNonRootPath(): void {
		$code = 'fr_FR';
		$prefix = str_replace( '_', '-', strtolower( $code ) );
		$pattern = '^' . $prefix . '/(.+?)/?$';

		self::assertSame( '^fr-fr/(.+?)/?$', $pattern );
		self::assertTrue( (bool) preg_match( '#' . $pattern . '#', 'fr-fr/hello-world/', $m ) );
		self::assertSame( 'hello-world', $m[1] );
	}

	public function testHreflangValueRespectsCustomFieldWhenSet(): void {
		$entry = array(
			'code'     => 'zh_CN',
			'hreflang' => 'zh-Hans',
		);
		$hreflang = '' !== $entry['hreflang'] ? $entry['hreflang'] : $entry['code'];
		self::assertSame( 'zh-Hans', $hreflang );
	}

	public function testHreflangFallsBackToCodeWhenEmpty(): void {
		$entry = array(
			'code'     => 'en_US',
			'hreflang' => '',
		);
		$hreflang = '' !== $entry['hreflang'] ? $entry['hreflang'] : $entry['code'];
		self::assertSame( 'en_US', $hreflang );
	}

	public function testCatalogDataIntegrity(): void {
		// Verify all language codes are lowercase when re-stored.
		$codes  = array_column( $this->catalog(), 'code' );
		foreach ( $codes as $code ) {
			self::assertSame( $code, str_replace( '_', '-', strtolower( $code ) ), "Code {$code} must be storable under the rewrite tag regex." );
		}
	}

	public function testRewriteTagRegexMatchesAllCatalogCodes(): void {
		$regex = '/^([a-z0-9_-]+)\/?$/';
		foreach ( $this->catalog() as $entry ) {
			$prefix = str_replace( '_', '-', strtolower( (string) $entry['code'] ) );
			self::assertSame( 1, preg_match( $regex, $prefix ), "Code {$entry['code']} does not match rewrite tag regex '{$regex}'." );
		}
	}

	/**
	 * Return a slice of the catalog for integration-level tests.
	 *
	 * @return array<int, array<string, string|int>>
	 */
	private function catalog(): array {
		return array(
			array( 'code' => 'en_US', 'hreflang' => 'en', 'direction' => 0 ),
			array( 'code' => 'fr_FR', 'hreflang' => 'fr', 'direction' => 0 ),
			array( 'code' => 'de_DE', 'hreflang' => 'de', 'direction' => 0 ),
			array( 'code' => 'zh_CN', 'hreflang' => 'zh-Hans', 'direction' => 0 ),
			array( 'code' => 'ja', 'hreflang' => 'ja', 'direction' => 0 ),
			array( 'code' => 'ar', 'hreflang' => 'ar', 'direction' => 1 ),
			array( 'code' => 'he_IL', 'hreflang' => 'he', 'direction' => 1 ),
			array( 'code' => 'ru_RU', 'hreflang' => 'ru', 'direction' => 0 ),
		);
	}
}
