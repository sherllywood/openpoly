<?php
/**
 * Service provider for the Engine module (A-03).
 *
 * Wires OpenAiCompatibleEngine into the DI container.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Engine;

use OpenPoly\Bootstrap\HookRegistrar;
use OpenPoly\Bootstrap\ServiceProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the Engine module.
 *
 * @since 1.0.0-dev
 */
final class EngineServiceProvider extends ServiceProvider {

	/**
	 * Bind factories.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->container->set(
			EngineGateway::class,
			static function (): EngineGateway {
				return OpenAiCompatibleEngine::from_options();
			}
		);

		$this->container->set(
			OpenAiCompatibleEngine::class,
			static function (): OpenAiCompatibleEngine {
				return OpenAiCompatibleEngine::from_options();
			}
		);
	}

	/**
	 * No hooks to register.
	 *
	 * @param HookRegistrar $registrar Hook registrar.
	 * @return void
	 */
	public function boot( HookRegistrar $registrar ): void {
		unset( $registrar );
	}
}
