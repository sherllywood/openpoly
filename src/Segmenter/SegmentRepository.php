<?php
/**
 * Data-access object for op_segments.
 *
 * Handles CRUD for individual translatable segments within a
 * translation group.  Used by the ATE editor and XLIFF import/export.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Segmenter;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD operations on op_segments.
 *
 * @since 1.0.0-dev
 */
final class SegmentRepository {

	/**
	 * Load all segments for a (trid, language_code) pair.
	 *
	 * Returns segments ordered by segment_index ascending.
	 *
	 * @param int    $trid          Translation group id.
	 * @param string $language_code Language code, e.g. "en_US".
	 * @return array<int, array<string, mixed>>
	 */
	public function load( int $trid, string $language_code ): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe int/string.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}op_segments
				 WHERE trid = %d AND language_code = %s
				 ORDER BY segment_index ASC",
				$trid,
				$language_code
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Save (upsert) segments for a batch of segments.
	 *
	 * Existing segments not present in the incoming list are left
	 * untouched (they may be translations for another language).
	 *
	 * @param int                                                      $trid          Translation group id.
	 * @param string                                                   $element_type  Element type, e.g. "post_post".
	 * @param int                                                      $element_id    Element id.
	 * @param string                                                   $language_code Language code.
	 * @param array<int, array{segment_index:int, source_text:string, md5:string}> $segments      Segments to upsert.
	 * @return int Number of segments upserted.
	 */
	public function save( int $trid, string $element_type, int $element_id, string $language_code, array $segments ): int {
		global $wpdb;

		$now   = current_time( 'mysql', true );
		$count = 0;

		foreach ( $segments as $seg ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}op_segments
					 WHERE trid = %d AND language_code = %s AND segment_index = %d",
					$trid,
					$language_code,
					$seg['segment_index']
				)
			);

			if ( null !== $existing ) {
				// Segment exists. Check if source has changed.
				$old = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT md5, status FROM {$wpdb->prefix}op_segments WHERE id = %d",
						$existing
					),
					ARRAY_A
				);

				$needs_update = 0;
				$status       = (int) ( $old['status'] ?? 0 );
				if ( is_array( $old ) && $old['md5'] !== $seg['md5'] ) {
					// Source changed — mark as needs_update unless already untranslated.
					$needs_update = ( 0 !== $status ) ? 1 : 0;
				}

				// phpcs:disable WordPress.DB
				$wpdb->update(
					$wpdb->prefix . 'op_segments',
					array(
						'source_text'  => $seg['source_text'],
						'md5'          => $seg['md5'],
						'needs_update' => $needs_update,
						'updated_at'   => $now,
					),
					array( 'id' => $existing ),
					array( '%s', '%s', '%d', '%s' ),
					array( '%d' )
				);
				// phpcs:enable
			} else {
				// Insert new segment.
				// phpcs:disable WordPress.DB
				$wpdb->insert(
					$wpdb->prefix . 'op_segments',
					array(
						'trid'             => $trid,
						'element_type'     => $element_type,
						'element_id'       => $element_id,
						'segment_index'    => $seg['segment_index'],
						'language_code'    => $language_code,
						'source_text'      => $seg['source_text'],
						'translated_text'  => '',
						'status'           => 0,
						'md5'              => $seg['md5'],
						'needs_update'     => 0,
						'created_at'       => $now,
						'updated_at'       => $now,
					),
					array( '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
				);
				// phpcs:enable
			}
			++$count;
		}

		return $count;
	}

	/**
	 * Update the translated text and status for a single segment.
	 *
	 * @param int    $segment_id      Segment row id.
	 * @param string $translated_text Translated text.
	 * @param int    $status          New status (0, 1, 2, 10).
	 * @param int    $translator_id   User id.
	 * @return void
	 */
	public function update_translation( int $segment_id, string $translated_text, int $status, int $translator_id = 0 ): void {
		global $wpdb;

		// phpcs:disable WordPress.DB
		$wpdb->update(
			$wpdb->prefix . 'op_segments',
			array(
				'translated_text' => $translated_text,
				'status'          => $status,
				'translator_id'   => $translator_id,
				'needs_update'    => 0,
				'updated_at'      => current_time( 'mysql', true ),
			),
			array( 'id' => $segment_id ),
			array( '%s', '%d', '%d', '%d', '%s' ),
			array( '%d' )
		);
		// phpcs:enable
	}

	/**
	 * Count segments by status for a (trid, language_code) pair.
	 *
	 * @param int    $trid          Translation group id.
	 * @param string $language_code Language code.
	 * @return array{total:int, translated:int, draft:int, untranslated:int}
	 */
	public function count_by_status( int $trid, string $language_code ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) as cnt FROM {$wpdb->prefix}op_segments
				 WHERE trid = %d AND language_code = %s
				 GROUP BY status",
				$trid,
				$language_code
			),
			ARRAY_A
		);

		$counts = array(
			'total'        => 0,
			'translated'   => 0,
			'draft'        => 0,
			'untranslated' => 0,
		);

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$cnt = (int) $row['cnt'];
				$counts['total'] += $cnt;

				switch ( (int) $row['status'] ) {
					case 2:
					case 10:
						$counts['translated'] += $cnt;
						break;
					case 1:
						$counts['draft'] += $cnt;
						break;
					default:
						$counts['untranslated'] += $cnt;
						break;
				}
			}
		}

		return $counts;
	}
}
