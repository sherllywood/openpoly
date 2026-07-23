<?php
/**
 * Test: Catalog.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Language;

use OpenPoly\Language\Catalog;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Language\Catalog
 */
final class CatalogTest extends TestCase {

	public function testHasAtLeast65PresetLanguages(): void {
		self::assertGreaterThanOrEqual( 65, count( Catalog::all() ) );
	}

	public function testEveryEntryHasRequiredKeys(): void {
		$required = array( 'code', 'english_name', 'native_name', 'locale', 'hreflang', 'direction', 'flag' );
		foreach ( Catalog::all() as $entry ) {
			foreach ( $required as $key ) {
				self::assertArrayHasKey( $key, $entry, sprintf( 'Catalog entry %s missing key %s.', $entry['code'] ?? '?', $key ) );
			}
		}
	}

	public function testEveryCodeIsUnique(): void {
		$codes = array_column( Catalog::all(), 'code' );
		self::assertSame( count( $codes ), count( array_unique( $codes ) ), 'Catalog codes must be unique.' );
	}

	public function testDirectionIsZeroOrOne(): void {
		foreach ( Catalog::all() as $entry ) {
			$d = (int) $entry['direction'];
			self::assertContains( $d, array( 0, 1 ), sprintf( 'Language %s has invalid direction %d.', $entry['code'], $d ) );
		}
	}

	public function testRtlCodesAreCorrectlyFlagged(): void {
		$rtl = Catalog::rtl_codes();
		self::assertContains( 'ar', $rtl );
		self::assertContains( 'he_IL', $rtl );
		self::assertContains( 'fa_IR', $rtl );
		self::assertContains( 'ur', $rtl );
		self::assertNotContains( 'en_US', $rtl );
		self::assertNotContains( 'zh_CN', $rtl );
	}

	public function testFindReturnsEntryByCode(): void {
		$entry = Catalog::find( 'zh_CN' );
		self::assertNotNull( $entry );
		self::assertSame( 'Chinese (Simplified)', $entry['english_name'] );
		self::assertSame( '简体中文', $entry['native_name'] );
	}

	public function testFindReturnsNullForUnknownCode(): void {
		self::assertNull( Catalog::find( 'xx_XX' ) );
	}

	public function testEveryEntryHasNonEmptyNativeName(): void {
		foreach ( Catalog::all() as $entry ) {
			self::assertNotEmpty( $entry['native_name'], sprintf( 'Language %s has empty native_name.', $entry['code'] ) );
		}
	}
}
