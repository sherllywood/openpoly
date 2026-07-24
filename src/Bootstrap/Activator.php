<?php
/**
 * Plugin activation + bootstrap entry point.
 *
 * - M-01: registers schema version option, no DB writes yet.
 * - M-02: wires up DI container + service providers + hook registrar.
 * - M-03: runs dbDelta to install the first three tables.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Bootstrap;

use OpenPoly\Admin\AdminServiceProvider;
use OpenPoly\DB\Database;
use OpenPoly\Language\LanguageServiceProvider;
use OpenPoly\NavMenu\NavMenuServiceProvider;
use OpenPoly\Query\QueryServiceProvider;
use OpenPoly\Seo\HreflangServiceProvider;
use OpenPoly\Switcher\SwitcherServiceProvider;
use OpenPoly\Translation\TranslationServiceProvider;
use OpenPoly\Url\ContextServiceProvider;
use OpenPoly\Url\UrlServiceProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin activator and runtime bootstrap.
 *
 * @since 0.5.0-dev
 */
final class Activator {

	/**
	 * Container singleton instance.
	 *
	 * Exposed for tests and advanced callers; production code should
	 * receive the container via the ServiceProvider constructor.
	 *
	 * @var Container|null
	 */
	private static ?Container $container = null;

	/**
	 * Run on plugin activation.
	 *
	 * Creates the three core tables and records the schema version.
	 * Idempotent: a re-activation re-runs dbDelta, which is a no-op
	 * when the schema already matches.
	 *
	 * @since 0.5.0-dev
	 * @return void
	 */
	public static function on_activation(): void {
		Database::install();
	}

	/**
	 * Run on plugins_loaded (priority 1).
	 *
	 * Constructs the DI container, registers every ServiceProvider,
	 * then boots them. Idempotent: safe to call multiple times.
	 *
	 * @since 0.5.0-dev
	 * @return void
	 */
	public static function init(): void {
		if ( null !== self::$container ) {
			return;
		}

		$container = new Container();
		$registrar = new HookRegistrar();
		$providers = self::providers();

		foreach ( $providers as $provider_class ) {
			$container->set(
				$provider_class,
				static function ( Container $c ) use ( $provider_class ): ServiceProvider {
					return new $provider_class( $c );
				}
			);

			$provider = $container->get( $provider_class );
			$provider->register();
			$provider->boot( $registrar );
		}

		self::$container = $container;
	}

	/**
	 * Return the DI container; null if init() has not run yet.
	 *
	 * @since 0.5.0-dev
	 * @return Container|null
	 */
	public static function container(): ?Container {
		return self::$container;
	}

	/**
	 * Default list of service providers, in registration order.
	 *
	 * Modules that need a provider append their class here.
	 *
	 * @since 0.5.0-dev
	 * @return array<int, class-string<ServiceProvider>>
	 */
	private static function providers(): array {
		return array(
			LanguageServiceProvider::class,
			TranslationServiceProvider::class,
			UrlServiceProvider::class,
			ContextServiceProvider::class,
			QueryServiceProvider::class,
			HreflangServiceProvider::class,
			SwitcherServiceProvider::class,
			NavMenuServiceProvider::class,
			AdminServiceProvider::class,
		);
	}
}
