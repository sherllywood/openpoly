<?php
/**
 * Test: Activator.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Bootstrap;

use Brain\Monkey;
use Brain\Monkey\Functions;
use OpenPoly\Bootstrap\Activator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Bootstrap\Activator
 */
final class ActivatorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function testActivationRegistersSchemaVersionWhenMissing(): void {
		$added = false;

		Functions\when( 'get_option' )->justReturn( false );
		Functions\when( 'add_option' )->alias(
			static function ( string $name, $value, string $deprecated = '', string $autoload = 'yes' ) use ( &$added ): bool {
				if ( 'openpoly_schema_version' === $name ) {
					$added = true;
				}
				return true;
			}
		);

		Activator::on_activation();

		self::assertTrue( $added, 'Activator should register openpoly_schema_version option on first activation.' );
	}
}
