<?php
/**
 * Language data-access object.
 *
 * All persistence for op_languages goes through this class. The
 * rest of the codebase must not call $wpdb directly for languages.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Language;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD operations for op_languages.
 *
 * @since 0.5.0-dev
 */
final class Repository {

	/**
	 * Unprefixed table name.
	 */
	private const TABLE = 'op_languages';

	/**
	 * Insert or update a language by code.
	 *
	 * @param array<string, string|int> $data Row data.
	 * @return int The row id.
	 */
	public function upsert( array $data ): int {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;
		$now   = current_time( 'mysql', true );

		$row = array(
			'code'           => (string) $data['code'],
			'english_name'   => (string) $data['english_name'],
			'native_name'    => (string) $data['native_name'],
			'locale'         => isset( $data['locale'] ) ? (string) $data['locale'] : '',
			'hreflang'       => isset( $data['hreflang'] ) ? (string) $data['hreflang'] : (string) $data['code'],
			'default_locale' => '',
			'text_direction' => isset( $data['direction'] ) ? (int) $data['direction'] : 0,
			'flag'           => isset( $data['flag'] ) ? (string) $data['flag'] : '',
			'is_active'      => isset( $data['is_active'] ) ? (int) $data['is_active'] : 0,
			'is_default'     => isset( $data['is_default'] ) ? (int) $data['is_default'] : 0,
			'is_hidden'      => isset( $data['is_hidden'] ) ? (int) $data['is_hidden'] : 0,
			'sort_order'     => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
			'created_at'     => $now,
			'updated_at'     => $now,
		);

		$format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s' );

		$existing = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$wpdb->prefix}op_languages WHERE code = %s", $row['code'] ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		);

		if ( $existing ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . self::TABLE,
				$row,
				array( 'id' => (int) $existing ),
				$format,
				array( '%d' )
			);
			return (int) $existing;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . self::TABLE,
			$row,
			$format
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Get one language by code.
	 *
	 * @param string $code Language code, e.g. "zh_CN".
	 * @return array<string, mixed>|null
	 */
	public function find_by_code( string $code ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}op_languages WHERE code = %s", $code ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * List all active languages, ordered by sort_order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list_active(): array {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}op_languages WHERE is_active = 1 ORDER BY sort_order ASC, english_name ASC",
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * List all languages (active or not), ordered by sort_order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list_all(): array {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}op_languages ORDER BY sort_order ASC, english_name ASC",
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Set the active flag for a language by id.
	 *
	 * @param int  $id     The op_languages row id.
	 * @param bool $active New active state.
	 * @return void
	 */
	public function set_active( int $id, bool $active ): void {
		global $wpdb;

		$now = current_time( 'mysql', true );
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . self::TABLE,
			array(
				'is_active'  => $active ? 1 : 0,
				'updated_at' => $now,
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}
}
