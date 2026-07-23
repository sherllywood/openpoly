<?php
/**
 * Test: Translation\TranslationGroup construction with mock Repository.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Translation;

use OpenPoly\Translation\TranslationGroup;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Translation\TranslationGroup
 */
final class TranslationGroupMockTest extends TestCase {

	public function testEmptyGroupHasZeroSizeAndNoSource(): void {
		$group = TranslationGroup::from_rows( 1, array() );

		self::assertSame( 0, $group->size() );
		self::assertSame( array(), $group->all() );
		self::assertNull( $group->source() );
		self::assertNull( $group->source_language() );
		self::assertSame( array(), $group->languages() );
	}

	public function testSingleSourceOnlyGroup(): void {
		$group = TranslationGroup::from_rows(
			1,
			array(
				array(
					'element_id'           => 42,
					'language_code'        => 'zh_CN',
					'source_language_code' => null,
				),
			)
		);

		self::assertSame( 1, $group->size() );
		self::assertSame( 42, $group->source() );
		self::assertSame( 'zh_CN', $group->source_language() );
		self::assertTrue( $group->has( 'zh_CN' ) );
		self::assertFalse( $group->has( 'en_US' ) );
	}

	public function testGetReturnsExactElementId(): void {
		$group = TranslationGroup::from_rows(
			1,
			array(
				array(
					'element_id'           => 100,
					'language_code'        => 'zh_CN',
					'source_language_code' => null,
				),
				array(
					'element_id'           => 200,
					'language_code'        => 'en_US',
					'source_language_code' => 'zh_CN',
				),
			)
		);

		self::assertSame( 100, $group->get( 'zh_CN' ) );
		self::assertSame( 200, $group->get( 'en_US' ) );
		self::assertNull( $group->get( 'missing' ) );
	}
}
