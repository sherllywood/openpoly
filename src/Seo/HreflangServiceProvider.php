<?php
/**
 * Service provider for the SEO / hreflang module.
 *
 * Wires Hreflang into the DI container, binds its lazy
 * dependencies (trid lookup + group load) and registers the
 * wp_head hook.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Seo;

use OpenPoly\Bootstrap\Container;
use OpenPoly\Bootstrap\HookRegistrar;
use OpenPoly\Bootstrap\ServiceProvider;
use OpenPoly\Language\LanguageManager;
use OpenPoly\Translation\Repository;
use OpenPoly\Translation\TranslationGroup;
use OpenPoly\Url\LanguageUrlFilter;
use OpenPoly\Url\UrlRouter;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the SEO module.
 *
 * @since 0.5.0-dev
 */
final class HreflangServiceProvider extends ServiceProvider {

	/**
	 * Bind the Hreflang factory.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->container->set(
			Hreflang::class,
			static function ( Container $c ): Hreflang {
				$hreflang = new Hreflang(
					$c->get( UrlRouter::class ),
					$c->get( LanguageManager::class ),
					$c->get( LanguageUrlFilter::class )
				);

				$translations = $c->get( Repository::class );
				$hreflang->set_trid_resolver(
					static fn ( string $type, int $id ): ?int => $translations->get_trid( $type, $id )
				);
				$hreflang->set_group_loader(
					static fn ( int $trid ): ?TranslationGroup => TranslationGroup::load( $trid, $translations )
				);

				return $hreflang;
			}
		);
	}

	/**
	 * Register the wp_head hook for hreflang output.
	 *
	 * @param HookRegistrar $registrar Hook registrar (unused here; hook goes via add_action directly).
	 * @return void
	 */
	public function boot( HookRegistrar $registrar ): void {
		unset( $registrar );
		$this->container->get( Hreflang::class )->register_hooks();
	}
}
