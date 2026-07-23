<?php
/**
 * Translation status: per (trid, language) lifecycle state.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Translation;

defined( 'ABSPATH' ) || exit;

/**
 * Status of a translation, persisted in op_translation_status.
 *
 * @since 0.5.0-dev
 */
enum Status: int {

	/** No element exists yet for the (trid, language). */
	case NOT_TRANSLATED = 0;

	/** A job is in progress and the engine or a translator is filling in segments. */
	case IN_PROGRESS = 1;

	/** All segments are translated; the post can be published. */
	case TRANSLATED = 2;

	/** The source content fingerprint changed; the translation may be out of date. */
	case NEEDS_UPDATE = 3;

	/** The post is a duplicate (shadow) of the source, kept in sync automatically. */
	case DUPLICATE = 4;

	/** Engine translation finished; awaiting a human reviewer. */
	case AWAITING_REVIEW = 10;
}
