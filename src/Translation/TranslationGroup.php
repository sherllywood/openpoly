<?php
/**
 * Translation Group domain object.
 *
 * Wraps a trid and the elements it contains. Pure in-memory, no
 * database access. Construct via TranslationGroup::load( $trid )
 * or TranslationGroup::create( $source_element_type, $source_element_id,
 * $source_language_code ).
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Translation;

defined( 'ABSPATH' ) || exit;

/**
 * A translation group: one source element plus any number of
 * translated variants indexed by language_code.
 *
 * @since 0.5.0-dev
 */
final class TranslationGroup {

	/**
	 * Translation group id, primary key on op_translations.
	 *
	 * @var int
	 */
	private int $trid;

	/**
	 * Elements indexed by language code.
	 *
	 * @var array<string, array{element_id:int, language_code:string, source_language_code:?string}>
	 */
	private array $elements = array();

	/**
	 * Private constructor; use from_rows() or load().
	 *
	 * @param int                                                                                   $trid  Translation group id.
	 * @param array<int, array{element_id:int, language_code:string, source_language_code:?string}> $rows  Rows fetched from op_translations.
	 */
	private function __construct( int $trid, array $rows ) {
		$this->trid = $trid;
		foreach ( $rows as $row ) {
			$this->elements[ $row['language_code'] ] = $row;
		}
	}

	/**
	 * Load an existing group from the repository.
	 *
	 * @param int        $trid       Translation group id.
	 * @param Repository $repository Data-access object.
	 * @return self|null Null when the trid has no rows.
	 */
	public static function load( int $trid, Repository $repository ): ?self {
		$rows = $repository->list_by_trid( $trid );
		if ( empty( $rows ) ) {
			return null;
		}
		return new self( $trid, $rows );
	}

	/**
	 * Construct an in-memory group from already-fetched rows.
	 *
	 * Useful for tests and for callers that have already queried the
	 * repository.
	 *
	 * @param int                                                                                   $trid Translation group id.
	 * @param array<int, array{element_id:int, language_code:string, source_language_code:?string}> $rows Rows fetched from op_translations.
	 * @return self
	 */
	public static function from_rows( int $trid, array $rows ): self {
		return new self( $trid, $rows );
	}

	/**
	 * Return the group's trid.
	 *
	 * @return int
	 */
	public function trid(): int {
		return $this->trid;
	}

	/**
	 * Return every language in the group, sorted alphabetically.
	 *
	 * @return array<int, string>
	 */
	public function languages(): array {
		return array_keys( $this->elements );
	}

	/**
	 * Return the element id for a given language, or null if absent.
	 *
	 * @param string $language_code Language code, e.g. "en_US".
	 * @return int|null
	 */
	public function get( string $language_code ): ?int {
		return $this->elements[ $language_code ]['element_id'] ?? null;
	}

	/**
	 * Return the source-language element id (the one with
	 * source_language_code IS NULL), or null if not set.
	 *
	 * @return int|null
	 */
	public function source(): ?int {
		foreach ( $this->elements as $row ) {
			if ( null === $row['source_language_code'] ) {
				return $row['element_id'];
			}
		}
		return null;
	}

	/**
	 * Return the source-language code, or null.
	 *
	 * @return string|null
	 */
	public function source_language(): ?string {
		foreach ( $this->elements as $row ) {
			if ( null === $row['source_language_code'] ) {
				return $row['language_code'];
			}
		}
		return null;
	}

	/**
	 * Return every element as [ language_code => element_id ].
	 *
	 * @return array<string, int>
	 */
	public function all(): array {
		$out = array();
		foreach ( $this->elements as $lang => $row ) {
			$out[ $lang ] = $row['element_id'];
		}
		return $out;
	}

	/**
	 * Whether the group has a translation in the given language.
	 *
	 * @param string $language_code Language code, e.g. "en_US".
	 * @return bool
	 */
	public function has( string $language_code ): bool {
		return isset( $this->elements[ $language_code ] );
	}

	/**
	 * Return the count of languages in the group.
	 *
	 * @return int
	 */
	public function size(): int {
		return count( $this->elements );
	}
}
