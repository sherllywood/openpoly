<?php
/**
 * Translation Group data-access object.
 *
 * Persists op_translations rows. This is the heart of the WPML-style
 * trid model: every translatable element belongs to one group, and the
 * group identifies the set of language variants.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Translation;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD on op_translations.
 *
 * @since 0.5.0-dev
 */
final class Repository {

	/**
	 * Unprefixed table name.
	 */
	private const TABLE = 'op_translations';

	/**
	 * Return the source element (element_type, element_id) for a trid.
	 *
	 * The source element is the row where source_language_code IS NULL.
	 *
	 * @param int $trid Translation group id.
	 * @return array{element_type:string, element_id:int}|null
	 */
	public function get_source_element( int $trid ): ?array {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT element_type, element_id FROM {$wpdb->prefix}op_translations
				 WHERE trid = %d AND source_language_code IS NULL",
				$trid
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		return array(
			'element_type' => (string) $row['element_type'],
			'element_id'   => (int) $row['element_id'],
		);
	}

	/**
	 * Look up the trid for an existing element.
	 *
	 * @param string $element_type Element type, e.g. "post_post", "tax_category".
	 * @param int    $element_id   Post id, term id, or attachment id.
	 * @return int|null Null when the element has no group yet.
	 */
	public function get_trid( string $element_type, int $element_id ): ?int {
		global $wpdb;

		$trid = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT trid FROM {$wpdb->prefix}op_translations WHERE element_type = %s AND element_id = %d LIMIT 1",
				$element_type,
				$element_id
			)
		);

		return null === $trid ? null : (int) $trid;
	}

	/**
	 * Look up the element id for one (trid, element_type, language).
	 *
	 * @param int    $trid          Translation group id.
	 * @param string $element_type  Element type, e.g. "post_post".
	 * @param string $language_code Language code, e.g. "en_US".
	 * @return int|null Null when no element exists for the group in that language.
	 */
	public function get_element_id( int $trid, string $element_type, string $language_code ): ?int {
		global $wpdb;

		$id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT element_id FROM {$wpdb->prefix}op_translations WHERE trid = %d AND element_type = %s AND language_code = %s LIMIT 1",
				$trid,
				$element_type,
				$language_code
			)
		);

		return null === $id ? null : (int) $id;
	}

	/**
	 * Add an element to a translation group, or to a new group if $trid is null.
	 *
	 * @param int|null    $trid                 Existing trid, or null to start a new group.
	 * @param string      $element_type         Element type, e.g. "post_post".
	 * @param int         $element_id           Post id, term id, or attachment id.
	 * @param string      $language_code        Language code, e.g. "en_US".
	 * @param string|null $source_language_code Null for the original-language element.
	 * @return int The trid the element ended up in.
	 */
	public function add( ?int $trid, string $element_type, int $element_id, string $language_code, ?string $source_language_code = null ): int {
		global $wpdb;

		if ( null === $trid ) {
			$trid = $this->next_trid();
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . self::TABLE,
			array(
				'element_type'         => $element_type,
				'element_id'           => $element_id,
				'trid'                 => $trid,
				'language_code'        => $language_code,
				'source_language_code' => $source_language_code,
			),
			array( '%s', '%d', '%d', '%s', '%s' )
		);

		return $trid;
	}

	/**
	 * Remove an element from its translation group.
	 *
	 * @param string $element_type Element type, e.g. "post_post".
	 * @param int    $element_id   Post id, term id, or attachment id.
	 * @return void
	 */
	public function remove( string $element_type, int $element_id ): void {
		global $wpdb;

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . self::TABLE,
			array(
				'element_type' => $element_type,
				'element_id'   => $element_id,
			),
			array( '%s', '%d' )
		);
	}

	/**
	 * Return every element in a translation group, ordered by language_code.
	 *
	 * @param int $trid Translation group id.
	 * @return array<int, array{element_id:int, language_code:string, source_language_code:?string}>
	 */
	public function list_by_trid( int $trid ): array {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT element_id, language_code, source_language_code FROM {$wpdb->prefix}op_translations WHERE trid = %d ORDER BY language_code ASC",
				$trid
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'element_id'           => (int) $row['element_id'],
				'language_code'        => (string) $row['language_code'],
				'source_language_code' => null === $row['source_language_code'] ? null : (string) $row['source_language_code'],
			);
		}
		return $out;
	}

	/**
	 * Allocate the next trid.
	 *
	 * Strategy: MAX(trid) + 1, computed in a single SQL statement. The
	 * table is not in a high-write hot path during normal request
	 * handling, so MAX-based allocation is fine for M-05; a separate
	 * counter row may replace it later if benchmarks demand it.
	 *
	 * @return int
	 */
	private function next_trid(): int {
		global $wpdb;

		$next = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COALESCE(MAX(trid), 0) + 1 FROM {$wpdb->prefix}op_translations"
		);
		return $next;
	}
}
