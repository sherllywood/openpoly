<?php
/**
 * Test: LanguageManager (with a fake repository, no real DB).
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Language;

use OpenPoly\Language\LanguageManager;
use OpenPoly\Language\Repository;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Language\LanguageManager
 */
final class LanguageManagerTest extends TestCase {

	public function testActiveLanguagesAreCached(): void {
		$repo      = $this->createMock( Repository::class );
		$languages = array(
			$this->row( 1, 'en_US', true, false ),
			$this->row( 2, 'zh_CN', true, true ),
		);
		$repo->expects( self::once() )
			->method( 'list_active' )
			->willReturn( $languages );

		$manager = new LanguageManager( $repo );

		self::assertSame( $languages, $manager->active_languages() );
		self::assertSame( $languages, $manager->active_languages() );
	}

	public function testDefaultLanguageCodeReturnsFlaggedRow(): void {
		$repo = $this->createMock( Repository::class );
		$repo->method( 'list_active' )->willReturn(
			array(
				$this->row( 1, 'en_US', true, false ),
				$this->row( 2, 'zh_CN', true, true ),
			)
		);

		$manager = new LanguageManager( $repo );

		self::assertSame( 'zh_CN', $manager->default_language_code() );
	}

	public function testDefaultLanguageCodeReturnsNullWhenNoneSet(): void {
		$repo = $this->createMock( Repository::class );
		$repo->method( 'list_active' )->willReturn(
			array(
				$this->row( 1, 'en_US', true, false ),
			)
		);

		$manager = new LanguageManager( $repo );

		self::assertNull( $manager->default_language_code() );
	}

	public function testActivateClearsCache(): void {
		$repo = $this->createMock( Repository::class );
		$repo->expects( self::exactly( 2 ) )
			->method( 'list_active' )
			->willReturn( array() );
		$repo->expects( self::once() )
			->method( 'set_active' )
			->with( 42, true );

		$manager = new LanguageManager( $repo );
		$manager->active_languages();
		$manager->activate( 42 );
		$manager->active_languages();
	}

	public function testDeactivateClearsCache(): void {
		$repo = $this->createMock( Repository::class );
		$repo->expects( self::exactly( 2 ) )
			->method( 'list_active' )
			->willReturn( array() );
		$repo->expects( self::once() )
			->method( 'set_active' )
			->with( 7, false );

		$manager = new LanguageManager( $repo );
		$manager->active_languages();
		$manager->deactivate( 7 );
		$manager->active_languages();
	}

	public function testInstallCatalogInsertsOnlyMissingRows(): void {
		$repo = $this->createMock( Repository::class );

		$existing_codes = array( 'en_US', 'zh_CN' );
		$repo->method( 'find_by_code' )->willReturnCallback(
			static function ( string $code ) use ( $existing_codes ): ?array {
				return in_array( $code, $existing_codes, true ) ? array( 'code' => $code ) : null;
			}
		);

		$upserts = array();
		$repo->method( 'upsert' )->willReturnCallback(
			static function ( array $row ) use ( &$upserts ): int {
				$upserts[] = $row['code'];
				return 1;
			}
		);

		$manager = new LanguageManager( $repo );
		$inserted = $manager->install_catalog();

		self::assertSame( 0, $inserted, 'When every preset already exists, install_catalog inserts nothing.' );
		self::assertSame( array(), $upserts );
	}

	/**
	 * Build a fake op_languages row for use in mocks.
	 *
	 * @param int    $id
	 * @param string $code
	 * @param bool   $is_active
	 * @param bool   $is_default
	 * @return array<string, mixed>
	 */
	private function row( int $id, string $code, bool $is_active, bool $is_default ): array {
		return array(
			'id'         => $id,
			'code'       => $code,
			'is_active'  => $is_active ? 1 : 0,
			'is_default' => $is_default ? 1 : 0,
		);
	}
}
