<?php
/**
 * Result of resolving a (trid, language) request.
 *
 * Carries the element id to render (or null for fallback) and the
 * mode that produced this result.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Translation;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable result of a ContentTranslator::resolve() call.
 *
 * @since 0.5.0-dev
 */
final class ContentResolution {

	/**
	 * Construct a resolution result.
	 *
	 * @param int         $element_id     Post / term id to render, or null for fallback.
	 * @param ContentMode $mode           Mode that produced this result.
	 * @param string|null $language_code  Target language of the request, preserved for fallback URLs.
	 */
	public function __construct(
		public readonly ?int $element_id,
		public readonly ContentMode $mode,
		public readonly ?string $language_code,
	) {
	}
}
