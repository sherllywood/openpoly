<?php
/**
 * Service provider for the Language module.
 *
 * Registers Repository + LanguageManager in the DI container and
 * installs the preset catalog on plugin activation.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Language;

use OpenPoly\Bootstrap\Container;
use OpenPoly\Bootstrap\HookRegistrar;
use OpenPoly\Bootstrap\ServiceProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the Language module into the container.
 *
 * @since 0.5.0-dev
 */
final class LanguageServiceProvider extends ServiceProvider {

	/**
	 * Bind factories.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->container->set(
			Repository::class,
			static function (): Repository {
				return new Repository();
			}
		);

		$this->container->set(
			LanguageManager::class,
			static function ( Container $c ): LanguageManager {
				return new LanguageManager( $c->get( Repository::class ) );
			}
		);
	}

	/**
	 * Run the catalog installer on first activation. Boot is
	 * idempotent: it only inserts languages that do not yet exist.
	 *
	 * @param HookRegistrar $registrar Hook registrar (unused in M-04).
	 * @return void
	 */
	public function boot( HookRegistrar $registrar ): void {
		$manager = $this->container->get( LanguageManager::class );
		$manager->install_catalog();
	}
}
