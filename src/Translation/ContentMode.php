<?php
/**
 * How an element relates to its translation-group source.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Translation;

defined( 'ABSPATH' ) || exit;

/**
 * Content mode of a single (trid, language) tuple.
 *
 * The three modes are mutually exclusive at any moment in time; a
 * translation group can contain elements in different modes for
 * different languages.
 *
 * @since 0.5.0-dev
 */
enum ContentMode: string {

	/**
	 * Independent translation: a separate post that is maintained
	 * by translators.
	 */
	case TRANSLATED = 'translated';

	/**
	 * A shadow post that mirrors the source-language content
	 * automatically on every save.
	 */
	case DUPLICATE = 'duplicate';

	/**
	 * No element exists for this language; the source-language
	 * post is rendered with the current language URL preserved.
	 */
	case FALLBACK = 'fallback';
}
