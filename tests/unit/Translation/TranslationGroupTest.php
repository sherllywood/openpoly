<?php
/**
 * Test: TranslationGroup.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Translation;

use OpenPoly\Translation\Repository;
use OpenPoly\Translation\TranslationGroup;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Translation\TranslationGroup
 */
final class TranslationGroupTest extends TestCase {

	public function testFromRowsIndexesByLanguageCode(): void {
		$group = TranslationGroup::from_rows(
			42,
			array(
				$this->row( 100, 'zh_CN', null ),
				$this->row( 200, 'en_US', 'zh_CN' ),
			)
		);

		self::assertSame( 100, $group->get( 'zh_CN' ) );
		self::assertSame( 200, $group->get( 'en_US' ) );
		self::assertNull( $group->get( 'fr_FR' ) );
	}

	public function testSourceReturnsElementWithNullSourceLanguage(): void {
		$group = TranslationGroup::from_rows(
			42,
			array(
				$this->row( 100, 'zh_CN', null ),
				$this->row( 200, 'en_US', 'zh_CN' ),
			)
		);

		self::assertSame( 100, $group->source() );
		self::assertSame( 'zh_CN', $group->source_language() );
	}

	public function testSourceIsNullWhenNoOriginalElement(): void {
		$group = TranslationGroup::from_rows(
			42,
			array(
				$this->row( 200, 'en_US', 'zh_CN' ),
				$this->row( 300, 'fr_FR', 'en_US' ),
			)
		);

		self::assertNull( $group->source() );
		self::assertNull( $group->source_language() );
	}

	public function testAllReturnsLanguageMap(): void {
		$group = TranslationGroup::from_rows(
			42,
			array(
				$this->row( 100, 'zh_CN', null ),
				$this->row( 200, 'en_US', 'zh_CN' ),
				$this->row( 300, 'fr_FR', 'en_US' ),
			)
		);

		self::assertSame(
			array(
				'zh_CN' => 100,
				'en_US' => 200,
				'fr_FR' => 300,
			),
			$group->all()
		);
	}

	public function testLanguagesReturnsSortedKeys(): void {
		$group = TranslationGroup::from_rows(
			42,
			array(
				$this->row( 300, 'fr_FR', 'en_US' ),
				$this->row( 100, 'zh_CN', null ),
				$this->row( 200, 'en_US', 'zh_CN' ),
			)
		);

		self::assertSame( array( 'en_US', 'fr_FR', 'zh_CN' ), $group->languages() );
	}

	public function testHasReturnsTrueOnlyForExistingLanguages(): void {
		$group = TranslationGroup::from_rows(
			42,
			array( $this->row( 100, 'zh_CN', null ) )
		);

		self::assertTrue( $group->has( 'zh_CN' ) );
		self::assertFalse( $group->has( 'en_US' ) );
	}

	public function testSizeCountsLanguages(): void {
		$group = TranslationGroup::from_rows(
			42,
			array(
				$this->row( 100, 'zh_CN', null ),
				$this->row( 200, 'en_US', 'zh_CN' ),
			)
		);

		self::assertSame( 2, $group->size() );
	}

	public function testLoadReturnsNullForUnknownTrid(): void {
		$repo = $this->createMock( Repository::class );
		$repo->expects( self::once() )
			->method( 'list_by_trid' )
			->with( 999 )
			->willReturn( array() );

		self::assertNull( TranslationGroup::load( 999, $repo ) );
	}

	public function testLoadReturnsGroupWithPopulatedRows(): void {
		$repo = $this->createMock( Repository::class );
		$repo->expects( self::once() )
			->method( 'list_by_trid' )
			->with( 7 )
			->willReturn(
				array(
					$this->row( 1, 'zh_CN', null ),
					$this->row( 2, 'en_US', 'zh_CN' ),
				)
			);

		$group = TranslationGroup::load( 7, $repo );

		self::assertNotNull( $group );
		self::assertSame( 7, $group->trid() );
		self::assertSame( 1, $group->source() );
		self::assertSame( 2, $group->get( 'en_US' ) );
	}

	/**
	 * Build a fake element row.
	 *
	 * @param int         $element_id
	 * @param string      $language_code
	 * @param string|null $source_language_code
	 * @return array{element_id:int, language_code:string, source_language_code:?string}
	 */
	private function row( int $element_id, string $language_code, ?string $source_language_code ): array {
		return array(
			'element_id'           => $element_id,
			'language_code'        => $language_code,
			'source_language_code' => $source_language_code,
		);
	}
}
