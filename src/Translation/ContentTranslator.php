<?php
/**
 * Decides which element to render for a (trid, language) pair.
 *
 * The three modes map to:
 *   - TRANSLATED  : an explicit, independent translation exists
 *   - DUPLICATE   : the source content is mirrored into a shadow post
 *   - FALLBACK    : no record for the language; render the source
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Translation;

defined( 'ABSPATH' ) || exit;

/**
 * Pure decision logic: given a trid and a target language, return
 * the (element_id, mode) pair that the request should render.
 *
 * No database access: callers (M-10 query interception layer)
 * fetch the candidate rows and pass them in. This keeps the policy
 * unit-testable without real MySQL.
 *
 * @since 0.5.0-dev
 */
final class ContentTranslator {

	/**
	 * Resolve a (trid, language) request.
	 *
	 * @param TranslationGroup $group         The translation group being requested.
	 * @param string           $language_code Target language code, e.g. "en_US".
	 * @return ContentResolution
	 */
	public function resolve( TranslationGroup $group, string $language_code ): ContentResolution {
		// Case 1: explicit translation in the target language.
		if ( $group->has( $language_code ) ) {
			return new ContentResolution(
				$group->get( $language_code ),
				ContentMode::TRANSLATED,
				$language_code,
			);
		}

		// Case 2: source language has an element but the target does not.
		$source_language = $group->source_language();
		$source_id       = $group->source();

		if ( null === $source_language || null === $source_id ) {
			// Group has no source. Render whatever the target has if any
			// (only possible if the only entry is also the target, which
			// is already handled in case 1).
			return new ContentResolution( null, ContentMode::FALLBACK, $language_code );
		}

		// Request is for the source language itself: render source.
		if ( $language_code === $source_language ) {
			return new ContentResolution(
				$source_id,
				ContentMode::TRANSLATED,
				$source_language,
			);
		}

		// No entry for the target language. Caller decides between
		// duplicate (shadow post it knows about) and fallback (404 /
		// render source). The decision lives in the post-type /
		// element-type handler (e.g. ContentType_Duplicate check),
		// not in this class; we report FALLBACK as the default.
		return new ContentResolution(
			$source_id,
			ContentMode::FALLBACK,
			$language_code,
		);
	}

	/**
	 * Convenience: classify a single (element_type, element_id, language)
	 * triple by looking up the trid and resolving the request.
	 *
	 * @param Repository $repository    Data-access object.
	 * @param string     $element_type  Element type, e.g. "post_post".
	 * @param int        $element_id    Post / term id.
	 * @param string     $language_code Target language code.
	 * @return ContentResolution|null Null when the element has no group.
	 */
	public function resolve_for_element( Repository $repository, string $element_type, int $element_id, string $language_code ): ?ContentResolution {
		$trid = $repository->get_trid( $element_type, $element_id );
		if ( null === $trid ) {
			return null;
		}
		$group = TranslationGroup::load( $trid, $repository );
		if ( null === $group ) {
			return null;
		}
		return $this->resolve( $group, $language_code );
	}
}
