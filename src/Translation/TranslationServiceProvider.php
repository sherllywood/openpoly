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
			StatusRepository::class,
			static function (): StatusRepository {
				return new StatusRepository();
			}
		);

		$this->container->set(
			ContentTranslator::class,
			static function (): ContentTranslator {
				return new ContentTranslator();
			}
		);

		$this->container->set(
			TranslationSync::class,
			static function ( Container $c ): TranslationSync {
				return new TranslationSync(
					$c->get( Repository::class ),
					$c->get( StatusRepository::class )
				);
			}
		);
	}

	/**
	 * Register the save_post hook from TranslationSync.
	 *
	 * @param HookRegistrar $registrar Hook registrar shared by all providers.
	 * @return void
	 */
	public function boot( HookRegistrar $registrar ): void {
		$registrar->register( $this->container->get( TranslationSync::class ) );
	}
}
