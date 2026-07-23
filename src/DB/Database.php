<?php
/**
 * Database installation and migration entry point.
 *
 * Idempotent: re-running install() is safe. Every call to install()
 * invokes dbDelta() for every table; dbDelta is a no-op if a table
 * already matches the current schema.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\DB;

defined( 'ABSPATH' ) || exit;

/**
 * Installs and upgrades the OpenPoly database schema.
 *
 * @since 0.5.0-dev
 */
final class Database {

	/**
	 * The option key that stores the schema version currently in use.
	 */
	public const SCHEMA_VERSION_OPTION = 'openpoly_schema_version';

	/**
	 * Run dbDelta() for every table in Schema::tables() and update the
	 * stored schema version. Safe to call multiple times.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( Schema::tables() as $name => $sql_template ) {
			$sql = str_replace( '{prefix}', $wpdb->prefix, $sql_template );
			dbDelta( $sql );
		}

		update_option( self::SCHEMA_VERSION_OPTION, Schema::VERSION );
	}

	/**
	 * Return the schema version currently stored in the database, or 0
	 * if the plugin has never been installed.
	 *
	 * @return int
	 */
	public static function current_version(): int {
		return (int) get_option( self::SCHEMA_VERSION_OPTION, 0 );
	}
}
