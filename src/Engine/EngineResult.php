<?php
/**
 * Engine translation result DTO.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Engine;

defined( 'ABSPATH' ) || exit;

/**
 * Engine translation result.
 *
 * @since 1.0.0-dev
 */
final class EngineResult {

	/**
	 * Map of segment_id => translated_text.
	 *
	 * @var array<int, string>
	 */
	public array $translations;

	/**
	 * Billing / usage info from the gateway.
	 *
	 * @var array{chars_billed:int, tokens_in:int, tokens_out:int, tokens_cached:int}
	 */
	public array $usage;

	/**
	 * Whether the gateway response was from the idempotency cache.
	 *
	 * @var bool
	 */
	public bool $is_cached;

	/**
	 * Constructor.
	 *
	 * @param array<int, string>                                                          $translations Segment translations.
	 * @param array{chars_billed:int, tokens_in:int, tokens_out:int, tokens_cached:int}   $usage        Usage info.
	 * @param bool                                                                        $is_cached    Whether cached.
	 */
	public function __construct( array $translations, array $usage = array(), bool $is_cached = false ) {
		$this->translations = $translations;
		$this->usage        = $usage;
		$this->is_cached    = $is_cached;
	}
}
