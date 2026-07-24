<?php
/**
 * Service provider for the Switcher module.
 *
 * Wires Switcher into the DI container and registers the
 * [openpoly_language_switcher] shortcode.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Switcher;

use OpenPoly\Bootstrap\Container;
use OpenPoly\Bootstrap\HookRegistrar;
use OpenPoly\Bootstrap\ServiceProvider;
use OpenPoly\Language\LanguageManager;
use OpenPoly\Url\LanguageUrlFilter;
use OpenPoly\Url\UrlRouter;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the Switcher module.
 *
 * @since 0.5.0-dev
 */
final class SwitcherServiceProvider extends ServiceProvider {

	/**
	 * Bind the switcher factory.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->container->set(
			Switcher::class,
			static function ( Container $c ): Switcher {
				return new Switcher(
					$c->get( UrlRouter::class ),
					$c->get( LanguageManager::class ),
					$c->get( LanguageUrlFilter::class )
				);
			}
		);
	}

	/**
	 * Register the shortcode.
	 *
	 * @param HookRegistrar $registrar Hook registrar (unused here; we use add_shortcode directly).
	 * @return void
	 */
	public function boot( HookRegistrar $registrar ): void {
		unset( $registrar );
		$this->container->get( Switcher::class )->register_hooks();
	}
}
