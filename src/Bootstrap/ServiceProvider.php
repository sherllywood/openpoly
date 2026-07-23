<?php
/**
 * Abstract base class for OpenPoly modules.
 *
 * Each module subclasses ServiceProvider and implements register()
 * (bind factories into the container) and optionally boot() (resolve
 * services and register hooks). Activator::init() calls register()
 * on every provider, then boot().
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Bootstrap;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for OpenPoly service providers.
 *
 * @since 0.5.0-dev
 */
abstract class ServiceProvider {

	/**
	 * The container instance.
	 *
	 * @var Container
	 */
	protected Container $container;

	/**
	 * Construct the provider with the DI container.
	 *
	 * @param Container $container The shared DI container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Bind factories into the container. Called for every provider
	 * during plugin boot, before boot() runs.
	 *
	 * @since 0.5.0-dev
	 * @return void
	 */
	abstract public function register(): void;

	/**
	 * Resolve services and register hooks. Called after every
	 * provider has registered. Default implementation is a no-op
	 * for modules that have no boot-time work.
	 *
	 * @since 0.5.0-dev
	 * @param HookRegistrar $registrar Hook registrar shared by all providers.
	 * @return void
	 */
	public function boot( HookRegistrar $registrar ): void {
		// No-op by default. Override to register hooks.
	}
}
