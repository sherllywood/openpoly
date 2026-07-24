<?php
/**
 * Service provider for the NavMenu module.
 *
 * Wires MenuSync into the DI container.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\NavMenu;

use OpenPoly\Bootstrap\Container;
use OpenPoly\Bootstrap\HookRegistrar;
use OpenPoly\Bootstrap\ServiceProvider;
use OpenPoly\Translation\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the NavMenu module.
 *
 * @since 0.5.0-dev
 */
final class NavMenuServiceProvider extends ServiceProvider {

	/**
	 * Bind the menu sync factory.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->container->set(
			MenuSync::class,
			static function ( Container $c ): MenuSync {
				return new MenuSync(
					$c->get( Repository::class )
				);
			}
		);
	}

	/**
	 * M-13 has no hooks to register yet (sync runs from admin UI).
	 *
	 * @param HookRegistrar $registrar Hook registrar (unused here).
	 * @return void
	 */
	public function boot( HookRegistrar $registrar ): void {
		unset( $registrar );
	}
}
