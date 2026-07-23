<?php
/**
 * Interface for classes that declare their WordPress hook bindings.
 *
 * Implementing this interface replaces scattered add_action() /
 * add_filter() calls in constructors. It also gives us a single
 * place to enumerate all hooks a module contributes to (handy for
 * documentation and disabling a module at runtime).
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Bootstrap;

defined( 'ABSPATH' ) || exit;

/**
 * Contract for a class that contributes WordPress hooks.
 *
 * @since 0.5.0-dev
 */
interface Hookable {

	/**
	 * Return the hooks this class wants registered.
	 *
	 * @return iterable<HookDefinition>
	 */
	public function hooks(): iterable;
}
