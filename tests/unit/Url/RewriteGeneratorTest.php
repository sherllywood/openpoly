<?php
/**
 * Test: RewriteGenerator.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Url;

use OpenPoly\Url\RewriteGenerator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Url\RewriteGenerator
 */
final class RewriteGeneratorTest extends TestCase {

	public function testRulesForLanguageNormalisesCode(): void {
		$rules = RewriteGenerator::rules_for_language( 'en_US' );

		self::assertSame( '^en-us/?$', $rules[0]['pattern'] );
		self::assertSame( 'index.php?lang=en_US', $rules[0]['replacement'] );
		self::assertSame( '^en-us/(.+?)/?$', $rules[1]['pattern'] );
		self::assertSame( 'index.php?lang=en_US&pagename=$matches[1]', $rules[1]['replacement'] );
	}

	public function testRulesForLanguageLowercasesPrefix(): void {
		$rules = RewriteGenerator::rules_for_language( 'zh_CN' );

		self::assertSame( '^zh-cn/?$', $rules[0]['pattern'] );
		self::assertStringContainsString( 'lang=zh_CN', $rules[1]['replacement'] );
	}

	public function testBuildRulesCoversAllLanguages(): void {
		$rules = RewriteGenerator::build_rules( array( 'en_US', 'fr_FR', 'de_DE' ) );

		self::assertCount( 6, $rules );
		self::assertArrayHasKey( '^en-us/?$', $rules );
		self::assertArrayHasKey( '^fr-fr/(.+?)/?$', $rules );
		self::assertArrayHasKey( '^de-de/?$', $rules );
	}

	public function testMergePutsLanguageRulesFirst(): void {
		$existing = array(
			'^wp-json/?$' => 'index.php?rest_route=/',
			'^page/([0-9]+)/?$' => 'index.php?&paged=$matches[1]',
		);

		$merged = RewriteGenerator::merge( $existing, array( 'en_US' ) );

		$keys = array_keys( $merged );
		self::assertSame( '^en-us/?$', $keys[0] );
		self::assertSame( '^en-us/(.+?)/?$', $keys[1] );
		// WP rules still present.
		self::assertArrayHasKey( '^wp-json/?$', $merged );
	}

	public function testMergeWithEmptyLanguagesReturnsExistingUnchanged(): void {
		$existing = array( 'foo' => 'bar' );
		self::assertSame( $existing, RewriteGenerator::merge( $existing, array() ) );
	}

	public function testMergeWithNullExistingReturnsOnlyLanguageRules(): void {
		$merged = RewriteGenerator::merge( null, array( 'en_US' ) );
		self::assertCount( 2, $merged, 'Null existing rules should produce only language rules.' );
		self::assertArrayHasKey( '^en-us/?$', $merged );
	}
}
