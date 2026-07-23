<?php
/**
 * Service provider for the Query module.
 *
 * Wires QueryFilter into the DI container and registers its hooks.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Query;

use OpenPoly\Bootstrap\Container;
use OpenPoly\Bootstrap\HookRegistrar;
use OpenPoly\Bootstrap\ServiceProvider;
use OpenPoly\Language\LanguageManager;
use OpenPoly\Url\UrlRouter;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the Query module.
 *
 * @since 0.5.0-dev
 */
final class QueryServiceProvider extends ServiceProvider {

	/**
	 * Bind the query filter factory.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->container->set(
			QueryFilter::class,
			static function ( Container $c ): QueryFilter {
				return new QueryFilter(
					$c->get( UrlRouter::class ),
					$c->get( LanguageManager::class )
				);
			}
		);
	}

	/**
	 * Register the WP hooks from QueryFilter.
	 *
	 * @param HookRegistrar $registrar Hook registrar (unused here; hooks go via add_action/add_filter directly).
	 * @return void
	 */
	public function boot( HookRegistrar $registrar ): void {
		unset( $registrar );
		$filter = $this->container->get( QueryFilter::class );
		$filter->register_hooks();
	}
}
