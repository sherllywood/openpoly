<?php
/**
 * Test: HookDefinition value object.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Bootstrap;

use OpenPoly\Bootstrap\HookDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Bootstrap\HookDefinition
 */
final class HookDefinitionTest extends TestCase {

	public function testDefaultsAreSensible(): void {
		$d = new HookDefinition( 'init', 'on_init' );

		self::assertSame( 'init', $d->hook );
		self::assertSame( 'on_init', $d->method );
		self::assertSame( 10, $d->priority );
		self::assertSame( 1, $d->accepted_args );
		self::assertFalse( $d->is_filter );
	}

	public function testCustomValuesAreStored(): void {
		$d = new HookDefinition(
			'the_content',
			'transform',
			99,
			2,
			true,
		);

		self::assertSame( 'the_content', $d->hook );
		self::assertSame( 'transform', $d->method );
		self::assertSame( 99, $d->priority );
		self::assertSame( 2, $d->accepted_args );
		self::assertTrue( $d->is_filter );
	}
}
