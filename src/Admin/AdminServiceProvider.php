<?php
/**
 * Service provider for the Admin module.
 *
 * Wires LanguageMetaBox, CreateTranslation, AteEditor, and EngineSettings
 * into the DI container and registers their hooks.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Admin;

use OpenPoly\Bootstrap\Container;
use OpenPoly\Bootstrap\HookRegistrar;
use OpenPoly\Bootstrap\ServiceProvider;
use OpenPoly\Language\LanguageManager;
use OpenPoly\Segmenter\Segmenter;
use OpenPoly\Segmenter\SegmentRepository;
use OpenPoly\Segmenter\XliffExport;
use OpenPoly\Segmenter\XliffImport;
use OpenPoly\Translation\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the Admin module.
 *
 * @since 0.5.0-dev
 */
final class AdminServiceProvider extends ServiceProvider {

	/**
	 * Bind factories.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->container->set(
			LanguageMetaBox::class,
			static function ( Container $c ): LanguageMetaBox {
				return new LanguageMetaBox(
					$c->get( LanguageManager::class ),
					$c->get( Repository::class )
				);
			}
		);

		$this->container->set(
			CreateTranslation::class,
			static function ( Container $c ): CreateTranslation {
				return new CreateTranslation(
					$c->get( LanguageManager::class ),
					$c->get( Repository::class )
				);
			}
		);

		$this->container->set(
			AteEditor::class,
			static function ( Container $c ): AteEditor {
				return new AteEditor(
					$c->get( Segmenter::class ),
					$c->get( SegmentRepository::class ),
					$c->get( Repository::class ),
					$c->get( XliffExport::class ),
					$c->get( XliffImport::class )
				);
			}
		);

		$this->container->set(
			EngineSettings::class,
			static function (): EngineSettings {
				return new EngineSettings();
			}
		);
	}

	/**
	 * Register hooks.
	 *
	 * @param HookRegistrar $registrar Hook registrar (unused here; we register add_action directly).
	 * @return void
	 */
	public function boot( HookRegistrar $registrar ): void {
		unset( $registrar );
		$this->container->get( LanguageMetaBox::class )->register_hooks();
		$this->container->get( CreateTranslation::class )->register_hooks();
		$this->container->get( AteEditor::class )->register_hooks();
		$this->container->get( EngineSettings::class )->register_hooks();
	}
}
