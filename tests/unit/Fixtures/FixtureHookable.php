<?php
/**
 * Fixture Hookable used in unit tests.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Fixtures;

use OpenPoly\Bootstrap\Hookable;
use OpenPoly\Bootstrap\HookDefinition;

/**
 * Test double: a Hookable that contributes three hooks.
 *
 * @since 0.5.0-dev
 */
final class FixtureHookable implements Hookable {

	/**
	 * @var int
	 */
	public int $action_calls = 0;

	/**
	 * @var int
	 */
	public int $filter_calls = 0;

	/**
	 * @var mixed
	 */
	public $filter_last_value = null;

	public function hooks(): iterable {
		return array(
			new HookDefinition( 'init', 'on_action', 10, 0, false ),
			new HookDefinition( 'the_title', 'on_filter', 20, 1, true ),
			new HookDefinition( 'wp_footer', 'on_action', 99, 0, false ),
		);
	}

	public function on_action(): void {
		++$this->action_calls;
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	public function on_filter( $value ) {
		++$this->filter_calls;
		$this->filter_last_value = $value;
		return '[filtered] ' . (string) $value;
	}
}
