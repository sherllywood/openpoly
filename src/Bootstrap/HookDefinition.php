<?php
/**
 * Value object describing a single WordPress hook registration.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Bootstrap;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable description of one hook to be registered.
 *
 * @since 0.5.0-dev
 */
final class HookDefinition {

	/**
	 * Construct a hook definition.
	 *
	 * @param string $hook          WordPress hook name.
	 * @param string $method        Method on the owning object to call.
	 * @param int    $priority      Hook priority (default 10).
	 * @param int    $accepted_args Number of arguments the callback accepts.
	 * @param bool   $is_filter     True to register as filter, false as action.
	 */
	public function __construct(
		public readonly string $hook,
		public readonly string $method,
		public readonly int $priority = 10,
		public readonly int $accepted_args = 1,
		public readonly bool $is_filter = false,
	) {
	}
}
