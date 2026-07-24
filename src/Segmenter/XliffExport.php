<?php
/**
 * Exports segments as XLIFF 2.0.
 *
 * Used by the ATE editor to package segments for offline translation
 * or engine batch submission.  Produces a standards-compliant XLIFF
 * 2.0 document with one <unit> per segment.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Segmenter;

use DOMDocument;

defined( 'ABSPATH' ) || exit;

/**
 * XLIFF 2.0 export handler.
 *
 * @since 1.0.0-dev
 */
final class XliffExport {

	/**
	 * Generate an XLIFF 2.0 XML string for a batch of segments.
	 *
	 * @param string $source_language Source language code, e.g. "en_US".
	 * @param string $target_language Target language code, e.g. "zh_CN".
	 * @param array<int, array<string, mixed>> $segments Rows from op_segments.  Each row must have id, source_text, translated_text, segment_index.
	 * @param array{post_title?:string, post_id?:int} $meta Optional metadata.
	 * @return string XLIFF 2.0 XML as string, or empty string on failure.
	 */
	public function export( string $source_language, string $target_language, array $segments, array $meta = array() ): string {
		if ( array() === $segments ) {
			return '';
		}

		$doc                     = new DOMDocument( '1.0', 'UTF-8' );
		$doc->formatOutput       = true;
		$doc->preserveWhiteSpace = false;

		$root = $doc->createElement( 'xliff' );
		$root->setAttribute( 'xmlns', 'urn:oasis:names:tc:xliff:document:2.0' );
		$root->setAttribute( 'version', '2.0' );
		$root->setAttribute( 'srcLang', $this->normalize_lang( $source_language ) );
		$root->setAttribute( 'trgLang', $this->normalize_lang( $target_language ) );
		$doc->appendChild( $root );

		$file = $doc->createElement( 'file' );
		$file->setAttribute( 'id', 'f1' );
		$root->appendChild( $file );

		// Notes element for metadata.
		if ( array() !== $meta ) {
			$notes = $doc->createElement( 'notes' );
			foreach ( $meta as $key => $value ) {
				$note = $doc->createElement( 'note' );
				$note->setAttribute( 'category', (string) $key );
				$note->textContent = (string) $value;
				$notes->appendChild( $note );
			}
			$file->appendChild( $notes );
		}

		foreach ( $segments as $segment ) {
			$unit = $doc->createElement( 'unit' );
			$unit->setAttribute( 'id', (string) ( $segment['id'] ?? 'u' . $segment['segment_index'] ) );

			$segment_elem = $doc->createElement( 'segment' );

			$source = $doc->createElement( 'source' );
			$source->textContent = (string) ( $segment['source_text'] ?? '' );
			$segment_elem->appendChild( $source );

			$target = $doc->createElement( 'target' );
			$translated = (string) ( $segment['translated_text'] ?? '' );
			if ( '' !== $translated ) {
				$target->textContent = $translated;
			}
			$segment_elem->appendChild( $target );

			$unit->appendChild( $segment_elem );
			$file->appendChild( $unit );
		}

		$xml = $doc->saveXML();
		return false !== $xml ? $xml : '';
	}

	/**
	 * Normalize a language code for XLIFF (underscore → hyphen, lowercase).
	 *
	 * @param string $code Language code, e.g. "en_US" or "zh_CN".
	 * @return string Normalized code, e.g. "en-us" or "zh-cn".
	 */
	private function normalize_lang( string $code ): string {
		return strtolower( str_replace( '_', '-', $code ) );
	}
}
