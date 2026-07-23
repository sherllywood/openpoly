<?php
/**
 * Test: HookRegistrar.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Bootstrap;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use OpenPoly\Bootstrap\HookRegistrar;
use OpenPoly\Tests\unit\Fixtures\FixtureHookable;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Bootstrap\HookRegistrar
 */
final class HookRegistrarTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function testRegisterInstallsAllHooksFromHookable(): void {
		$hookable  = new FixtureHookable();
		$registrar = new HookRegistrar();

		Actions\expectAdded( 'init' )->once();
		Actions\expectAdded( 'wp_footer' )->once();
		Filters\expectAdded( 'the_title' )->once();

		$registrar->register( $hookable );

		self::assertCount( 1, $registrar->registered_objects() );
	}

	public function testRegisterIgnoresNonHookableObjects(): void {
		$registrar = new HookRegistrar();
		$registrar->register( new \stdClass() );

		self::assertCount( 0, $registrar->registered_objects() );
	}

	public function testRegisterIsIdempotent(): void {
		$hookable  = new FixtureHookable();
		$registrar = new HookRegistrar();

		Actions\expectAdded( 'init' )->once();
		Actions\expectAdded( 'wp_footer' )->once();
		Filters\expectAdded( 'the_title' )->once();

		$registrar->register( $hookable );
		$registrar->register( $hookable );
		$registrar->register( $hookable );

		self::assertCount( 1, $registrar->registered_objects() );
	}
}
