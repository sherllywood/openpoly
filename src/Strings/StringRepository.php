<?php
/**
 * Data-access object for op_strings and op_string_translations.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Strings;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD operations on op_strings.
 *
 * @since 0.5.0-dev
 */
final class StringRepository {

	/**
	 * Unprefixed table names.
	 */
	private const TABLE_STRINGS      = 'op_strings';
	private const TABLE_TRANSLATIONS = 'op_string_translations';
	private const TABLE_POSITIONS    = 'op_string_positions';

	/**
	 * Insert a string if it does not already exist.
	 *
	 * @param array{text:string, context:string, domain:string, plural:string, fn:string} $entry Entry with text, context, domain, plural, fn keys.
	 * @return int String id.
	 */
	public function upsert( array $entry ): int {
		global $wpdb;

		$md5 = md5( $entry['domain'] . '|' . $entry['context'] . '|' . $entry['text'] );
		$now = current_time( 'mysql', true );

		$existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}op_strings WHERE domain_name_context_md5 = %s",
				$md5
			)
		);
		if ( null !== $existing ) {
			return (int) $existing;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . self::TABLE_STRINGS,
			array(
				'language'                => '',
				'context'                 => $entry['context'],
				'domain_name_context_md5' => $md5,
				'name'                    => $entry['text'],
				'value'                   => $entry['text'],
				'status'                  => 0,
				'domain'                  => $entry['domain'],
				'gettext_context'         => $entry['context'],
				'created_at'              => $now,
				'updated_at'              => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Record where a string was found (source file location).
	 *
	 * @param int    $string_id String ID.
	 * @param string $position  Position, e.g. "path/to/file.php:42".
	 * @param int    $kind      Kind: 1 = PHP source.
	 * @return void
	 */
	public function add_position( int $string_id, string $position, int $kind = 1 ): void {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . self::TABLE_POSITIONS,
			array(
				'string_id'        => $string_id,
				'kind'             => $kind,
				'position_in_page' => $position,
			),
			array( '%d', '%d', '%s' )
		);
	}

	/**
	 * Batch-load all translations for a (domain, language) pair.
	 *
	 * Used by GettextInterceptor to pre-fetch the entire set.
	 *
	 * @param string $domain   Text domain.
	 * @param string $language Language code.
	 * @return array<string, string> Map of md5 => translation.
	 */
	public function load_translations( string $domain, string $language ): array {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- table names from constants, safe.
			$wpdb->prepare(
				'SELECT s.domain_name_context_md5, t.value
				 FROM ' . $wpdb->prefix . self::TABLE_STRINGS . ' s
				 INNER JOIN ' . $wpdb->prefix . self::TABLE_TRANSLATIONS . ' t ON s.id = t.string_id
				 WHERE s.domain = %s AND t.language = %s AND t.status = 10',
				$domain,
				$language
			),
			ARRAY_A
		);

		$map = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$map[ (string) $row['domain_name_context_md5'] ] = (string) $row['value'];
			}
		}

		return $map;
	}
}
