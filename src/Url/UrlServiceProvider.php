<?php
/**
 * Service provider for the URL module.
 *
 * Wires UrlRouter + LanguageUrlFilter into the DI container and
 * registers their hooks on plugins_loaded.
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
 * Wires the URL module into the container.
 *
 * @since 0.5.0-dev
 */
final class UrlServiceProvider extends ServiceProvider {

	/**
	 * Bind factories.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->container->set(
			UrlRouter::class,
			static function ( Container $c ): UrlRouter {
				return new UrlRouter(
					$c->get( LanguageManager::class ),
					$c->get( Repository::class )
				);
			}
		);

		$this->container->set(
			LanguageUrlFilter::class,
			static function ( Container $c ): LanguageUrlFilter {
				return new LanguageUrlFilter(
					$c->get( LanguageManager::class ),
					$c->get( UrlRouter::class )
				);
			}
		);
	}

	/**
	 * Register URL hooks and the rewrite rules.
	 *
	 * @param HookRegistrar $registrar Hook registrar (unused here; hooks are registered directly).
	 * @return void
	 */
	public function boot( HookRegistrar $registrar ): void {
		unset( $registrar );

		$router = $this->container->get( UrlRouter::class );
		$filter = $this->container->get( LanguageUrlFilter::class );

		$router->register_hooks();
		$filter->register_hooks();

		add_action( 'init', array( $this, 'register_rewrite_rules' ), 20 );
		add_action( 'openpoly_language_changed', array( $this, 'flush_rewrite' ) );
	}

	/**
	 * Add the language rewrite rules on top of WP's existing rules.
	 *
	 * @return void
	 */
	public function register_rewrite_rules(): void {
		global $wp_rewrite;

		if ( ! isset( $wp_rewrite ) || ! is_object( $wp_rewrite ) ) {
			return;
		}

		$codes = array();
		foreach ( $this->container->get( LanguageManager::class )->active_languages() as $row ) {
			$codes[] = (string) $row['code'];
		}

		$wp_rewrite->rules = RewriteGenerator::merge( $wp_rewrite->rules, $codes );
	}

	/**
	 * Flush rewrite rules when languages change.
	 *
	 * @return void
	 */
	public function flush_rewrite(): void {
		flush_rewrite_rules( false );
	}
}
