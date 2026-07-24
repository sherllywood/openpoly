<?php
/**
 * Imports translations from XLIFF 2.0 back into op_segments.
 *
 * Reads an XLIFF 2.0 document, extracts each <unit>/<segment>,
 * and upserts the target text into the corresponding op_segments row.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Segmenter;

use DOMDocument;

defined( 'ABSPATH' ) || exit;

/**
 * XLIFF 2.0 import handler.
 *
 * @since 1.0.0-dev
 */
final class XliffImport {

	/**
	 * Parse XLIFF 2.0 XML and return an array of unit-level translations.
	 *
	 * Returns a list of associative arrays, each with keys: unit_id,
	 * source, target. The caller maps unit_id back to op_segments.id
	 * and calls SegmentRepository::update_translation().
	 *
	 * @param string $xml Raw XLIFF 2.0 XML string.
	 * @return array<int, array{unit_id:string, source:string, target:string}>
	 */
	public function parse( string $xml ): array {
		if ( '' === trim( $xml ) ) {
			return array();
		}

		$doc = new DOMDocument();
		// Disable external entity loading for security (XXE prevention).
		$old_internal = libxml_disable_entity_loader( true );
		$old_use_internal = libxml_use_internal_errors( true );

		$loaded = $doc->loadXML( $xml );
		libxml_clear_errors();
		libxml_disable_entity_loader( $old_internal );
		libxml_use_internal_errors( $old_use_internal );

		if ( ! $loaded ) {
			return array();
		}

		$results = array();
		$units   = $doc->getElementsByTagName( 'unit' );

		foreach ( $units as $unit ) {
			$unit_id   = $unit->getAttribute( 'id' );
			$segments  = $unit->getElementsByTagName( 'segment' );

			if ( 0 === $segments->length ) {
				continue;
			}

			$segment = $segments->item( 0 );
			if ( null === $segment ) {
				continue;
			}

			$source_nodes = $segment->getElementsByTagName( 'source' );
			$target_nodes = $segment->getElementsByTagName( 'target' );

			$source = $source_nodes->length > 0 ? trim( $source_nodes->item( 0 )->textContent ?? '' ) : '';
			$target = $target_nodes->length > 0 ? trim( $target_nodes->item( 0 )->textContent ?? '' ) : '';

			if ( '' !== $target ) {
				$results[] = array(
					'unit_id' => $unit_id,
					'source'  => $source,
					'target'  => $target,
				);
			}
		}

		return $results;
	}

	/**
	 * Apply parsed unit translations to op_segments via the repository.
	 *
	 * @param SegmentRepository                                        $repository Data-access object.
	 * @param array<int, array{unit_id:string, source:string, target:string}> $units Parsed units from parse().
	 * @return int Number of segments updated.
	 */
	public function apply( SegmentRepository $repository, array $units ): int {
		$updated = 0;

		foreach ( $units as $unit ) {
			$id = (int) $unit['unit_id'];
			if ( $id <= 0 ) {
				continue;
			}

			$repository->update_translation( $id, $unit['target'], 2 );
			++$updated;
		}

		return $updated;
	}
}
