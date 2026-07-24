<?php
/**
 * Service provider for the Strings module (A-01).
 *
 * Wires GettextScanner, StringRepository, and GettextInterceptor
 * into the DI container and registers the gettext hooks.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Strings;

use OpenPoly\Bootstrap\Container;
use OpenPoly\Bootstrap\HookRegistrar;
use OpenPoly\Bootstrap\ServiceProvider;
use OpenPoly\Language\LanguageManager;
use OpenPoly\Url\UrlRouter;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the Strings module.
 *
 * @since 0.5.0-dev
 */
final class StringsServiceProvider extends ServiceProvider {

	/**
	 * Bind factories.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->container->set(
			GettextScanner::class,
			static function (): GettextScanner {
				return new GettextScanner();
			}
		);

		$this->container->set(
			StringRepository::class,
			static function (): StringRepository {
				return new StringRepository();
			}
		);

		$this->container->set(
			GettextInterceptor::class,
			static function ( Container $c ): GettextInterceptor {
				return new GettextInterceptor(
					$c->get( StringRepository::class ),
					$c->get( LanguageManager::class ),
					$c->get( UrlRouter::class )
				);
			}
		);
	}

	/**
	 * Register the gettext hooks.
	 *
	 * @param HookRegistrar $registrar Hook registrar.
	 * @return void
	 */
	public function boot( HookRegistrar $registrar ): void {
		unset( $registrar );
		$this->container->get( GettextInterceptor::class )->register_hooks();
	}
}
