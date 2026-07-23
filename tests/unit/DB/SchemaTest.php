<?php
/**
 * Test: Schema.
 *
 * Verifies that every CREATE TABLE statement contains the columns and
 * indexes that the rest of the codebase relies on. These assertions
 * guard against silent regressions when SQL is edited.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\DB;

use OpenPoly\DB\Schema;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\DB\Schema
 */
final class SchemaTest extends TestCase {

	public function testVersionIsPositive(): void {
		self::assertGreaterThan( 0, Schema::VERSION, 'Schema::VERSION must be a positive integer.' );
	}

	public function testTablesArrayIsNonEmpty(): void {
		$tables = Schema::tables();

		self::assertNotEmpty( $tables, 'Schema::tables() must return at least one table.' );
	}

	public function testAllSqlContainsCreateTableKeyword(): void {
		foreach ( Schema::tables() as $name => $sql ) {
			self::assertStringContainsString( 'CREATE TABLE', $sql, sprintf( 'Table %s SQL must contain CREATE TABLE.', $name ) );
		}
	}

	public function testAllSqlContainsInnoDbEngine(): void {
		foreach ( Schema::tables() as $name => $sql ) {
			self::assertStringContainsString( 'ENGINE=InnoDB', $sql, sprintf( 'Table %s must use InnoDB engine.', $name ) );
		}
	}

	public function testAllSqlContainsUtf8mb4Charset(): void {
		foreach ( Schema::tables() as $name => $sql ) {
			self::assertStringContainsString( 'utf8mb4', $sql, sprintf( 'Table %s must use utf8mb4 charset.', $name ) );
		}
	}

	public function testAllSqlHasPrefixPlaceholder(): void {
		foreach ( Schema::tables() as $name => $sql ) {
			self::assertStringContainsString( '{prefix}', $sql, sprintf( 'Table %s must declare a {prefix} placeholder.', $name ) );
		}
	}

	public function testLanguagesTableHasExpectedColumns(): void {
		$sql = Schema::tables()['op_languages'];

		foreach ( array( 'id', 'code', 'english_name', 'native_name', 'is_active', 'is_default' ) as $column ) {
			self::assertStringContainsString( $column, $sql, sprintf( 'op_languages must have column %s.', $column ) );
		}
	}

	public function testTranslationsTableHasExpectedIndexes(): void {
		$sql = Schema::tables()['op_translations'];

		self::assertStringContainsString( 'UNIQUE KEY element_type_id_lang', $sql, 'op_translations must have the (element_type, element_id, language_code) unique index.' );
		self::assertStringContainsString( 'KEY trid_lang', $sql, 'op_translations must have the (trid, language_code) key.' );
	}

	public function testTranslationStatusTableHasMd5Fingerprint(): void {
		$sql = Schema::tables()['op_translation_status'];

		self::assertStringContainsString( 'md5', $sql, 'op_translation_status must store the source content fingerprint.' );
	}
}
