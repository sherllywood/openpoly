<?php
/**
 * Service provider for the URL Context module (M-11).
 *
 * Wires ContextResolver into the DI container and registers the
 * early hooks that resolve language for admin-ajax and REST.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Url;

use OpenPoly\Bootstrap\Container;
use OpenPoly\Bootstrap\HookRegistrar;
use OpenPoly\Bootstrap\ServiceProvider;
use OpenPoly\Language\LanguageManager;
use OpenPoly\Translation\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the URL Context module.
 *
 * @since 0.5.0-dev
 */
final class ContextServiceProvider extends ServiceProvider {

	/**
	 * Bind the resolver factory.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->container->set(
			ContextResolver::class,
			static function ( Container $c ): ContextResolver {
				return new ContextResolver(
					$c->get( UrlRouter::class ),
					$c->get( LanguageManager::class ),
					$c->get( Repository::class )
				);
			}
		);
	}

	/**
	 * Register the early hooks so admin-ajax and REST requests
	 * see the right language before any handler runs.
	 *
	 * @param HookRegistrar $registrar Hook registrar (unused here; hooks go via add_action directly).
	 * @return void
	 */
	public function boot( HookRegistrar $registrar ): void {
		unset( $registrar );
		$resolver = $this->container->get( ContextResolver::class );

		// Wrap the action callback so the return value is discarded.
		// WordPress action callbacks must not return anything.
		add_action(
			'admin_init',
			static function () use ( $resolver ): void {
				$resolver->resolve();
			},
			1
		);
		add_action(
			'rest_api_init',
			static function () use ( $resolver ): void {
				$resolver->resolve();
			},
			1
		);
	}
}
