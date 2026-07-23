<?php
/**
 * Plugin activation + bootstrap entry point.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Bootstrap;

defined( 'ABSPATH' ) || exit;

/**
 * Stub activator for M-01.
 *
 * - M-01: registers schema version option, no DB writes yet.
 * - M-02: will register the ServiceProvider and DI container.
 * - M-03: will run dbDelta for the first three tables.
 *
 * @since 0.5.0-dev
 */
final class Activator {

	public const SCHEMA_VERSION = 0;

	/**
	 * Run on plugin activation.
	 *
	 * @since 0.5.0-dev
	 * @return void
	 */
	public static function on_activation(): void {
		if ( false === get_option( 'openpoly_schema_version' ) ) {
			add_option( 'openpoly_schema_version', self::SCHEMA_VERSION );
		}
	}

	/**
	 * Run on plugins_loaded (priority 1).
	 *
	 * @since 0.5.0-dev
	 * @return void
	 */
	public static function init(): void {
		// M-02: load ServiceProvider here.
	}
}
