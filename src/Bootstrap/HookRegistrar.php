<?php
/**
 * Centralised hook registrar.
 *
 * Consumes every Hookable registered with the container and
 * installs its hooks via add_action / add_filter. Used by
 * ServiceProvider::boot() to wire modules in a single pass.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Bootstrap;

defined( 'ABSPATH' ) || exit;

/**
 * Registers hooks from all Hookable instances.
 *
 * @since 0.5.0-dev
 */
final class HookRegistrar {

	/**
	 * List of objects whose hooks have been registered.
	 *
	 * @var array<int, object>
	 */
	private array $registered = array();

	/**
	 * Register every hook declared by the given object.
	 *
	 * Non-Hookable objects are silently ignored so providers can
	 * pass any value through without first checking the type.
	 *
	 * @param object $instance Any object; only Hookable objects contribute hooks.
	 * @return void
	 */
	public function register( object $instance ): void {
		if ( ! $instance instanceof Hookable ) {
			return;
		}

		// Guard against double-registration.
		foreach ( $this->registered as $already ) {
			if ( $already === $instance ) {
				return;
			}
		}

		foreach ( $instance->hooks() as $definition ) {
			$this->install( $instance, $definition );
		}

		$this->registered[] = $instance;
	}

	/**
	 * Return the list of objects whose hooks have been registered.
	 *
	 * @return array<int, object>
	 */
	public function registered_objects(): array {
		return $this->registered;
	}

	/**
	 * Install a single hook definition.
	 *
	 * @param object         $instance   Object whose method should be called.
	 * @param HookDefinition $definition Hook description from Hookable::hooks().
	 * @return void
	 */
	private function install( object $instance, HookDefinition $definition ): void {
		$callback = array( $instance, $definition->method );

		if ( $definition->is_filter ) {
			add_filter(
				$definition->hook,
				$callback,
				$definition->priority,
				$definition->accepted_args
			);
			return;
		}

		add_action(
			$definition->hook,
			$callback,
			$definition->priority,
			$definition->accepted_args
		);
	}
}
