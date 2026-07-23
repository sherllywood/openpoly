<?php
/**
 * Minimal dependency-injection container.
 *
 * Lazy singletons keyed by id. The factory is called only on first
 * get(); the same instance is returned on every subsequent call.
 *
 * Why a hand-rolled container (~80 lines) instead of Pimple or similar:
 *   - WordPress has no dependency-isolation; any third-party package
 *     risks version conflicts with other plugins.
 *   - The container is internal: it is never exposed in the public API.
 *
 * @package OpenPoly
 *
 * phpcs:disable WordPress.Security.EscapeOutput.ExceptionNoEscape
 * The container is internal infrastructure; the only "output" it
 * produces is a RuntimeException message for development debugging.
 */

declare(strict_types=1);

namespace OpenPoly\Bootstrap;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Lazy singleton container.
 *
 * @since 0.5.0-dev
 */
final class Container {

	/**
	 * Factories keyed by id.
	 *
	 * @var array<string, callable(self): object>
	 */
	private array $factories = array();

	/**
	 * Resolved singletons keyed by id.
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * Register a factory for the given id.
	 *
	 * @param string                 $id      Service identifier (usually FQCN).
	 * @param callable(self): object $factory Factory receiving the container.
	 * @return void
	 */
	public function set( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Resolve (or return cached) instance for the given id.
	 *
	 * The optional @param-out lets callers narrow the returned type
	 * by passing a class-string<T> as the id.
	 *
	 * @template T of object
	 * @param class-string<T>|string $id Service id to resolve.
	 * @phpstan-return ($id is class-string<T> ? T : object)
	 * @throws RuntimeException When no factory has been registered for $id.
	 */
	public function get( string $id ): object {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new RuntimeException( 'OpenPoly container: no factory registered for "' . $id . '".' );
		}

		$instance               = ( $this->factories[ $id ] )( $this );
		$this->instances[ $id ] = $instance;

		return $instance;
	}

	/**
	 * Whether a factory is registered for the given id.
	 *
	 * @param string $id Service id to check.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] );
	}
}
