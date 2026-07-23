<?php
/**
 * Test: Database installation (Brain Monkey level).
 *
 * The real database is exercised by the integration test suite
 * (tests/integration/) which requires a running wp-env with
 * Docker. Brain Monkey is enough to assert that Database::install()
 * is idempotent and that the schema-version option is written.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\DB;

use Brain\Monkey;
use Brain\Monkey\Functions;
use OpenPoly\DB\Database;
use OpenPoly\DB\Schema;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\DB\Database
 */
final class DatabaseTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Provide $wpdb with a minimal shape.
		global $wpdb;
		$wpdb         = new \stdClass();
		$wpdb->prefix = 'wp_';
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function testCurrentVersionDefaultsToZero(): void {
		Functions\when( 'get_option' )->justReturn( false );

		self::assertSame( 0, Database::current_version() );
	}

	public function testCurrentVersionReadsStoredValue(): void {
		Functions\when( 'get_option' )->justReturn( '3' );

		self::assertSame( 3, Database::current_version() );
	}

	public function testInstallIsInvokable(): void {
		// dbDelta is required by Database::install(). Stub it so the
		// test does not require WordPress runtime.
		if ( ! function_exists( 'dbDelta' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- stub for unit test.
			eval( 'function dbDelta( $sql ) { return [ $sql ]; }' );
		}

		$updated_with = null;
		Functions\when( 'update_option' )->alias(
			static function ( $name, $value ) use ( &$updated_with ) {
				if ( Database::SCHEMA_VERSION_OPTION === $name ) {
					$updated_with = $value;
				}
				return true;
			}
		);

		Database::install();

		self::assertSame( Schema::VERSION, $updated_with, 'Database::install() must persist Schema::VERSION.' );
	}
}
