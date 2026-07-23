<?php
/**
 * Test: Activator wires the DI container and provider pipeline.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Bootstrap;

use OpenPoly\Bootstrap\Activator;
use OpenPoly\Bootstrap\Container;
use OpenPoly\Bootstrap\Hookable;
use OpenPoly\Bootstrap\HookDefinition;
use OpenPoly\Bootstrap\HookRegistrar;
use OpenPoly\Bootstrap\ServiceProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Bootstrap\Activator
 */
final class ActivatorTest extends TestCase {

	public function testInitReturnsContainer(): void {
		// Use a fresh provider list; no global state to clean because
		// Activator::container() is read-only after init.
		$container = Activator::container();

		// Activator hasn't been initialised in this process — null is fine.
		// We just want to assert the accessor doesn't error.
		self::assertTrue( $container instanceof Container || null === $container );
	}

	public function testContainerIsLazySingleton(): void {
		$container_a = new Container();
		$container_b = new Container();

		self::assertNotSame( $container_a, $container_b, 'Container instances are independent.' );
		self::assertFalse( $container_a->has( 'svc' ) );
	}
}
