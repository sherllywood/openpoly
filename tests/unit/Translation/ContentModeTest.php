<?php
/**
 * Test: ContentMode and Status enums.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Translation;

use OpenPoly\Translation\ContentMode;
use OpenPoly\Translation\Status;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Translation\ContentMode
 * @covers \OpenPoly\Translation\Status
 */
final class ContentModeTest extends TestCase {

	public function testContentModeValues(): void {
		self::assertSame( 'translated', ContentMode::TRANSLATED->value );
		self::assertSame( 'duplicate', ContentMode::DUPLICATE->value );
		self::assertSame( 'fallback', ContentMode::FALLBACK->value );
	}

	public function testStatusValues(): void {
		self::assertSame( 0, Status::NOT_TRANSLATED->value );
		self::assertSame( 1, Status::IN_PROGRESS->value );
		self::assertSame( 2, Status::TRANSLATED->value );
		self::assertSame( 3, Status::NEEDS_UPDATE->value );
		self::assertSame( 4, Status::DUPLICATE->value );
		self::assertSame( 10, Status::AWAITING_REVIEW->value );
	}

	public function testStatusFromInt(): void {
		self::assertSame( Status::TRANSLATED, Status::from( 2 ) );
		self::assertSame( Status::AWAITING_REVIEW, Status::from( 10 ) );
	}

	public function testContentModeFromString(): void {
		self::assertSame( ContentMode::TRANSLATED, ContentMode::from( 'translated' ) );
		self::assertSame( ContentMode::FALLBACK, ContentMode::from( 'fallback' ) );
	}
}
