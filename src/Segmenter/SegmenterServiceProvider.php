<?php
/**
 * Service provider for the Segmenter module (A-02).
 *
 * Wires Segmenter, SegmentRepository, XliffExport, and XliffImport
 * into the DI container.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Segmenter;

use OpenPoly\Bootstrap\Container;
use OpenPoly\Bootstrap\HookRegistrar;
use OpenPoly\Bootstrap\ServiceProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the Segmenter module.
 *
 * @since 1.0.0-dev
 */
final class SegmenterServiceProvider extends ServiceProvider {

	/**
	 * Bind factories.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->container->set(
			Segmenter::class,
			static function (): Segmenter {
				return new Segmenter();
			}
		);

		$this->container->set(
			SegmentRepository::class,
			static function (): SegmentRepository {
				return new SegmentRepository();
			}
		);

		$this->container->set(
			XliffExport::class,
			static function (): XliffExport {
				return new XliffExport();
			}
		);

		$this->container->set(
			XliffImport::class,
			static function (): XliffImport {
				return new XliffImport();
			}
		);
	}

	/**
	 * Register hooks (none needed for segmenter — it is request-driven).
	 *
	 * @param HookRegistrar $registrar Hook registrar.
	 * @return void
	 */
	public function boot( HookRegistrar $registrar ): void {
		unset( $registrar );
	}
}
