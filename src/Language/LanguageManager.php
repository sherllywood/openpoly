<?php
/**
 * Language domain service.
 *
 * Front-end for the rest of the codebase: preset catalog, repo
 * access, in-memory cache, activate/deactivate/default.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Language;

defined( 'ABSPATH' ) || exit;

/**
 * Service that owns language state for the request.
 *
 * @since 0.5.0-dev
 */
final class LanguageManager {

	/**
	 * Underlying data-access object.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * In-memory cache of the last list_active() result.
	 *
	 * @var array<int, array<string, mixed>>|null
	 */
	private ?array $cache = null;

	/**
	 * Construct the manager with its repository.
	 *
	 * @param Repository $repository Data-access object for op_languages.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Return every active language, ordered by sort_order.
	 *
	 * Result is cached for the lifetime of the request.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function active_languages(): array {
		if ( null === $this->cache ) {
			$this->cache = $this->repository->list_active();
		}
		return $this->cache;
	}

	/**
	 * Return the default language code, or null if none is set.
	 *
	 * @return string|null
	 */
	public function default_language_code(): ?string {
		foreach ( $this->active_languages() as $row ) {
			if ( 1 === (int) $row['is_default'] ) {
				return (string) $row['code'];
			}
		}
		return null;
	}

	/**
	 * Activate a language by id.
	 *
	 * @param int $id The op_languages row id.
	 * @return void
	 */
	public function activate( int $id ): void {
		$this->repository->set_active( $id, true );
		$this->cache = null;
	}

	/**
	 * Deactivate a language by id.
	 *
	 * @param int $id The op_languages row id.
	 * @return void
	 */
	public function deactivate( int $id ): void {
		$this->repository->set_active( $id, false );
		$this->cache = null;
	}

	/**
	 * Install the preset catalog into the database. Called once on
	 * plugin activation. Existing rows are preserved (only missing
	 * codes are inserted).
	 *
	 * @return int Number of rows inserted.
	 */
	public function install_catalog(): int {
		$inserted = 0;
		$order    = 0;
		foreach ( Catalog::all() as $entry ) {
			$existing            = $this->repository->find_by_code( (string) $entry['code'] );
			$entry['sort_order'] = $order++;
			if ( null === $existing ) {
				$this->repository->upsert( $entry );
				++$inserted;
			}
		}
		return $inserted;
	}
}
