<?php
/**
 * Test: Container.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Bootstrap;

use OpenPoly\Bootstrap\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \OpenPoly\Bootstrap\Container
 */
final class ContainerTest extends TestCase {

	public function testGetReturnsSameInstanceAcrossCalls(): void {
		$container = new Container();
		$container->set( 'svc', static fn(): object => new \stdClass() );

		$first  = $container->get( 'svc' );
		$second = $container->get( 'svc' );

		self::assertSame( $first, $second, 'Container should cache the resolved singleton.' );
	}

	public function testFactoryReceivesContainer(): void {
		$container = new Container();
		$container->set(
			'parent',
			static fn(): object => new \stdClass()
		);
		$container->set(
			'child',
			static function ( Container $c ): object {
				self::assertInstanceOf( Container::class, $c );
				return new \stdClass();
			}
		);

		$container->get( 'child' );
		$this->expectNotToPerformAssertions();
	}

	public function testGetWithoutFactoryThrows(): void {
		$container = new Container();

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'no factory registered' );
		$container->get( 'missing' );
	}

	public function testHasReturnsTrueOnlyForRegistered(): void {
		$container = new Container();
		self::assertFalse( $container->has( 'svc' ) );

		$container->set( 'svc', static fn(): object => new \stdClass() );
		self::assertTrue( $container->has( 'svc' ) );
	}

	public function testResetReplacesFactoryAndClearsCache(): void {
		$container = new Container();
		$container->set( 'svc', static fn(): object => new \stdClass() );
		$first = $container->get( 'svc' );

		$container->set( 'svc', static fn(): object => new \stdClass() );
		$second = $container->get( 'svc' );

		self::assertNotSame( $first, $second, 'Re-setting a factory must clear the cached singleton.' );
	}
}
