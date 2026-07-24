<?php
/**
 * Content segmenter for ATE-driven translation.
 *
 * Splits post content into translatable segments:
 *   1. Paragraphs (by double-newline or HTML block boundary).
 *   2. Sentences within each paragraph (punctuation + abbreviation exceptions).
 *
 * Each segment is returned with its (paragraph_index, sentence_index)
 * so the ATE editor can reconstruct the original document structure.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Segmenter;

defined( 'ABSPATH' ) || exit;

/**
 * Paragraph and sentence segmenter.
 *
 * @since 1.0.0-dev
 */
final class Segmenter {

	/**
	 * Split content into paragraphs, then each paragraph into sentences.
	 *
	 * Returns a flat list of segments, each tagged with paragraph_index
	 * and segment_index so the caller can reconstruct the original layout.
	 *
	 * @param string $content Raw post content (HTML + text).
	 * @return array<int, array{paragraph_index:int, segment_index:int, text:string, md5:string}>
	 */
	public function segment( string $content ): array {
		$paragraphs = $this->split_paragraphs( $content );
		$segments   = array();

		foreach ( $paragraphs as $p_idx => $paragraph ) {
			$text = trim( wp_strip_all_tags( $paragraph, true ) );
			if ( '' === $text ) {
				continue;
			}
			$sentences = $this->split_sentences( $text );
			foreach ( $sentences as $s_idx => $sentence ) {
				$trimmed = trim( $sentence );
				if ( '' === $trimmed ) {
					continue;
				}
				$segments[] = array(
					'paragraph_index' => $p_idx,
					'segment_index'   => $s_idx,
					'text'            => $trimmed,
					'md5'             => md5( $trimmed ),
				);
			}
		}

		return $segments;
	}

	/**
	 * Split content into paragraphs.
	 *
	 * Uses double-newline as primary separator. Falls back to single
	 * newline when no double-newline is present.
	 *
	 * @param string $content Raw content.
	 * @return array<int, string>
	 */
	public function split_paragraphs( string $content ): array {
		// Normalize line endings.
		$content = str_replace( "\r\n", "\n", $content );
		$content = str_replace( "\r", "\n", $content );

		// Split on double newline (paragraph boundary).
		$parts = preg_split( "/\n\s*\n/", $content );
		if ( false === $parts ) {
			return array( $content );
		}

		return array_values(
			array_filter(
				$parts,
				static function ( string $p ): bool {
					return '' !== trim( wp_strip_all_tags( $p, true ) );
				}
			)
		);
	}

	/**
	 * Split a single paragraph into sentences.
	 *
	 * Boundaries: . ! ? followed by whitespace + capital letter or
	 * string end. Handles common abbreviations (Mr., Mrs., Dr., etc.,
	 * U.S., U.K., i.e., e.g., etc.) to avoid false splits.
	 *
	 * Chinese period (。) is treated as a sentence boundary.
	 *
	 * @param string $text Plain text, no HTML.
	 * @return array<int, string>
	 */
	public function split_sentences( string $text ): array {
		// Protect abbreviations from being split.
		$abbreviations = array(
			'Mr.', 'Mrs.', 'Ms.', 'Dr.', 'Prof.', 'Sr.', 'Jr.',
			'St.', 'Ave.', 'Rd.', 'Blvd.',
			'U.S.', 'U.K.', 'E.U.',
			'i.e.', 'e.g.', 'etc.', 'vs.', 'viz.',
			'Inc.', 'Ltd.', 'Co.', 'Corp.',
			'Jan.', 'Feb.', 'Mar.', 'Apr.', 'Jun.', 'Jul.',
			'Aug.', 'Sep.', 'Sept.', 'Oct.', 'Nov.', 'Dec.',
		);

		$placeholders = array();
		foreach ( $abbreviations as $i => $abbr ) {
			$placeholder       = "{{ABBR{$i}}}";
			$placeholders[]    = $placeholder;
			$escaped           = preg_quote( $abbr, '/' );
			$text              = preg_replace( '/' . $escaped . '/u', $placeholder, $text );
		}

		// Split on sentence-ending punctuation followed by space + capital, or end of string.
		$parts = preg_split(
			'/(?<=[.!?。])\s+(?=[A-Z\x{4e00}-\x{9fff}\x{3040}-\x{309f}\x{30a0}-\x{30ff}])/u',
			$text
		);

		if ( false === $parts || count( $parts ) <= 1 ) {
			// Try simpler split: just on terminal punctuation at end of string.
			$parts = preg_split( '/(?<=[.!?。])\s*/u', $text );
		}

		if ( false === $parts ) {
			return array( $text );
		}

		// Restore abbreviations.
		$restored = array();
		foreach ( $parts as $part ) {
			$trimmed = trim(
				str_replace( $placeholders, $abbreviations, $part )
			);
			if ( '' !== $trimmed ) {
				$restored[] = $trimmed;
			}
		}

		return $restored;
	}
}
