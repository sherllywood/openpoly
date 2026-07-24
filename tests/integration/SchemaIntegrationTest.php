<?php
/**
 * Integration test for the database schema.
 *
 * Verifies that every table definition in Schema::tables()
 * contains the columns and indexes that the rest of the codebase
 * relies on. No real database is touched—the assertions are
 * purely string-based.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\Integration;

use OpenPoly\DB\Schema;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\DB\Schema
 */
final class SchemaIntegrationTest extends TestCase {

	public function testLanguagesTableHasAllRequiredColumns(): void {
		$sql = Schema::tables()['op_languages'];

		foreach ( array( 'id', 'code', 'english_name', 'native_name', 'locale', 'hreflang', 'text_direction', 'flag', 'is_active', 'is_default', 'is_hidden', 'sort_order', 'created_at', 'updated_at' ) as $col ) {
			self::assertStringContainsString( $col, $sql, "op_languages must have column '{$col}'." );
		}
	}

	public function testTranslationsTableHasCorrectUniqueKey(): void {
		$sql = Schema::tables()['op_translations'];

		self::assertStringContainsString( 'UNIQUE KEY element_type_id_lang', $sql );
		self::assertStringContainsString( 'KEY trid_lang', $sql );
		self::assertStringContainsString( 'KEY element_id', $sql );
	}

	public function testTranslationStatusDefaultsAreSane(): void {
		$sql = Schema::tables()['op_translation_status'];

		self::assertStringContainsString( "NOT NULL DEFAULT 0 COMMENT '0 not translated, 1 in progress, 2 translated, 3 needs update, 4 duplicate, 10 awaiting review'", $sql );
		self::assertStringContainsString( "char(32) NOT NULL DEFAULT ''", $sql );
	}

	public function testAllThreeTablesExistInSchema(): void {
		$tables = Schema::tables();

		self::assertArrayHasKey( 'op_languages', $tables );
		self::assertArrayHasKey( 'op_translations', $tables );
		self::assertArrayHasKey( 'op_translation_status', $tables );
		self::assertCount( 3, $tables );
	}

	public function testAllSqlIsDbDeltaCompatible(): void {
		foreach ( Schema::tables() as $name => $sql ) {
			// dbDelta requires: each column on its own line, KEY on own line,
			// PRIMARY KEY on own line, no FOREIGN KEY, ENGINE=InnoDB.
			self::assertStringNotContainsString( 'FOREIGN KEY', $sql, "Table {$name} must not use FOREIGN KEY constraints." );
			self::assertStringContainsString( 'ENGINE=InnoDB', $sql, "Table {$name} must use InnoDB engine." );
			self::assertStringContainsString( 'utf8mb4', $sql, "Table {$name} must use utf8mb4 charset." );
			self::assertStringContainsString( '{prefix}', $sql, "Table {$name} must have a {prefix} placeholder." );
		}
	}

	public function testSchemaVersionIsMonotonic(): void {
		self::assertGreaterThanOrEqual( 1, Schema::VERSION );
		self::assertIsInt( Schema::VERSION );
	}
}
