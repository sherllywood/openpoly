<?php
/**
 * Service provider for the Translation module.
 *
 * Wires the Translation Repository into the DI container.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Translation;

use OpenPoly\Bootstrap\Container;
use OpenPoly\Bootstrap\HookRegistrar;
use OpenPoly\Bootstrap\ServiceProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the Translation module into the container.
 *
 * @since 0.5.0-dev
 */
final class TranslationServiceProvider extends ServiceProvider {

	/**
	 * Bind the repository factory.
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
	}

	/**
	 * M-05 has no hooks to register yet.
	 *
	 * @param HookRegistrar $registrar Hook registrar (unused in M-05).
	 * @return void
	 */
	public function boot( HookRegistrar $registrar ): void {
		// No-op in M-05.
	}
}
