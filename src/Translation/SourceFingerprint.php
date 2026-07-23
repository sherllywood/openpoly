<?php
/**
 * Source content fingerprint.
 *
 * A stable md5 of the fields that, when changed, mark a translation
 * as "needs update" (FR-CORE-006). The fingerprint intentionally
 * ignores transient fields like post_modified so save_post does
 * not trigger spurious "needs update" on a no-op save.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Translation;

defined( 'ABSPATH' ) || exit;

/**
 * Pure helper that computes a deterministic md5 over a post.
 *
 * @since 0.5.0-dev
 */
final class SourceFingerprint {

	/**
	 * Fields that participate in the fingerprint, in stable order.
	 *
	 * @return array<int, string>
	 */
	public static function fields(): array {
		return array( 'post_title', 'post_content', 'post_excerpt', 'post_thumbnail' );
	}

	/**
	 * Compute the md5 fingerprint of a post.
	 *
	 * @param \WP_Post|object $post Post object (or anything that exposes the fields above).
	 * @return string
	 */
	public static function compute( object $post ): string {
		$parts = array();
		foreach ( self::fields() as $field ) {
			$value   = isset( $post->$field ) ? (string) $post->$field : '';
			$parts[] = $field . '=' . $value;
		}
		return md5( implode( '|', $parts ) );
	}

	/**
	 * Compute the md5 directly from the raw field values.
	 *
	 * Useful for tests and for callers that already have the parts in
	 * hand and do not want to instantiate a WP_Post.
	 *
	 * @param array<string, string|int> $parts Map of field => value.
	 * @return string
	 */
	public static function compute_from_array( array $parts ): string {
		$out = array();
		foreach ( self::fields() as $field ) {
			$out[] = $field . '=' . (string) ( $parts[ $field ] ?? '' );
		}
		return md5( implode( '|', $out ) );
	}
}
