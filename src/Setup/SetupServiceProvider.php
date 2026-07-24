<?php
/**
 * Service provider for the Setup module (M-16).
 *
 * Wires SetupWizard into the DI container and registers its
 * admin_init / admin_post hooks.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Setup;

use OpenPoly\Bootstrap\Container;
use OpenPoly\Bootstrap\HookRegistrar;
use OpenPoly\Bootstrap\ServiceProvider;
use OpenPoly\Language\LanguageManager;
use OpenPoly\Language\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the Setup module.
 *
 * @since 0.5.0-dev
 */
final class SetupServiceProvider extends ServiceProvider {

	/**
	 * Bind the wizard factory.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->container->set(
			SetupWizard::class,
			static function ( Container $c ): SetupWizard {
				return new SetupWizard(
					$c->get( LanguageManager::class ),
					$c->get( Repository::class )
				);
			}
		);
	}

	/**
	 * Register hooks and the admin page.
	 *
	 * @param HookRegistrar $registrar Hook registrar (unused here).
	 * @return void
	 */
	public function boot( HookRegistrar $registrar ): void {
		unset( $registrar );
		$wizard = $this->container->get( SetupWizard::class );
		$wizard->register_hooks();

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Admin_menu callback: register the wizard page in the admin menu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'OpenPoly Setup', 'openpoly' ),
			__( 'OpenPoly Setup', 'openpoly' ),
			'manage_options',
			'openpoly-setup',
			array( $this->container->get( SetupWizard::class ), 'render' ),
			'',
			80
		);
	}
}
