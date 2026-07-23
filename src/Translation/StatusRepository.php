<?php
/**
 * CRUD on op_translation_status.
 *
 * Tracks the per-(trid, language) lifecycle state of a translation.
 * The md5 column stores the source content fingerprint used to
 * detect source changes (FR-CORE-006).
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Translation;

defined( 'ABSPATH' ) || exit;

/**
 * Status data-access object.
 *
 * @since 0.5.0-dev
 */
final class StatusRepository {

	/**
	 * Unprefixed table name.
	 */
	private const TABLE = 'op_translation_status';

	/**
	 * Look up the status for one (trid, language) pair.
	 *
	 * @param int    $trid          Translation group id.
	 * @param string $language_code Language code, e.g. "en_US".
	 * @return array<string, mixed>|null
	 */
	public function find( int $trid, string $language_code ): ?array {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}op_translation_status WHERE trid = %d AND language_code = %s",
				$trid,
				$language_code
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert or update a status row.
	 *
	 * @param int    $trid                Translation group id.
	 * @param string $language_code       Language code, e.g. "en_US".
	 * @param Status $status              New lifecycle status.
	 * @param string $md5                Source content fingerprint.
	 * @param int    $translator_id      User id, 0 for engine / system.
	 * @param string $translation_service "local" / "engine" / "xliff".
	 * @return void
	 */
	public function upsert( int $trid, string $language_code, Status $status, string $md5 = '', int $translator_id = 0, string $translation_service = 'local' ): void {
		global $wpdb;

		$now    = current_time( 'mysql', true );
		$row    = array(
			'trid'                => $trid,
			'language_code'       => $language_code,
			'status'              => $status->value,
			'translation_service' => $translation_service,
			'translator_id'       => $translator_id,
			'md5'                 => $md5,
			'needs_update'        => Status::NEEDS_UPDATE === $status ? 1 : 0,
			'created_at'          => $now,
			'updated_at'          => $now,
		);
		$format = array( '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s' );

		$existing = $this->find( $trid, $language_code );
		if ( $existing ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . self::TABLE,
				$row,
				array(
					'trid'          => $trid,
					'language_code' => $language_code,
				),
				$format,
				array( '%d', '%s' )
			);
			return;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . self::TABLE,
			$row,
			$format
		);
	}

	/**
	 * Mark every (trid, language) row whose md5 differs from the
	 * supplied current fingerprint as needs_update.
	 *
	 * Used by the source-fingerprint check (FR-CORE-006).
	 *
	 * @param int    $trid        Translation group id.
	 * @param string $current_md5 Current source content fingerprint.
	 * @return int Number of rows marked as needs_update.
	 */
	public function mark_stale( int $trid, string $current_md5 ): int {
		global $wpdb;

		$now = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}op_translation_status SET needs_update = 1, status = %d, updated_at = %s WHERE trid = %d AND md5 <> %s AND md5 <> ''",
				Status::NEEDS_UPDATE->value,
				$now,
				$trid,
				$current_md5
			)
		);
	}
}
