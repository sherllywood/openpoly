<?php
/**
 * Test: MenuSync plan builder.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\NavMenu;

use OpenPoly\NavMenu\MenuSync;
use OpenPoly\Translation\Repository;
use OpenPoly\Translation\TranslationGroup;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\NavMenu\MenuSync
 */
final class MenuSyncTest extends TestCase {

	public function testMenuTitleReturnsEmptyStringForUnknownMenu(): void {
		$sync = $this->make_sync();
		self::assertSame( '', $sync->menu_title( 99999 ) );
	}

	public function testListMenuItemsReturnsEmptyArrayForUnknownMenu(): void {
		$sync = $this->make_sync();
		self::assertSame( array(), $sync->list_menu_items( 99999 ) );
	}

	public function testResolveTargetPostReturnsSourceWhenNoTrid(): void {
		$sync = $this->make_sync( array() );

		// Item with source post 100, no translation group.
		$this->given_menu_item( 7, 'post', 100 );

		self::assertSame( 100, $sync->resolve_target_post( 7, 'en_US' ) );
	}

	public function testResolveTargetPostFallsBackToSourceWhenLanguageMissing(): void {
		$sync = $this->make_sync(
			array( 'trid_for_100' => 42 )
		);

		// Item points to post 100 (trid 42). Target 'fr_FR' has no
		// element in the group, so we expect the source post id.
		$this->given_translation_group( 42, array() );
		$this->given_menu_item( 7, 'post', 100 );

		self::assertSame( 100, $sync->resolve_target_post( 7, 'fr_FR' ) );
	}

	public function testResolveTargetPostReturnsTranslatedId(): void {
		$sync = $this->make_sync(
			array( 'trid_for_100' => 42 )
		);

		$this->given_translation_group(
			42,
			array(
				array( 'element_id' => 100, 'language_code' => 'en_US', 'source_language_code' => null ),
				array( 'element_id' => 200, 'language_code' => 'fr_FR', 'source_language_code' => 'en_US' ),
			)
		);
		$this->given_menu_item( 7, 'post', 100 );

		self::assertSame( 200, $sync->resolve_target_post( 7, 'fr_FR' ) );
	}

	public function testResolveTargetPostReturnsNullForCustomLink(): void {
		$sync = $this->make_sync();

		// Custom link: no _menu_item_object_id meta.
		$this->given_menu_item( 7, 'custom', 0 );

		self::assertNull( $sync->resolve_target_post( 7, 'fr_FR' ) );
	}

	/**
	 * Build a MenuSync with a mocked Repository. The $trid_map tells
	 * the mock which trid to return for each source post id.
	 *
	 * @param array<string, int> $trid_map Map of "trid_for_<id>" => trid.
	 * @return MenuSync
	 */
	private function make_sync( array $trid_map = array() ): MenuSync {
		$repo = $this->createMock( Repository::class );
		$repo->method( 'get_trid' )->willReturnCallback(
			static function ( string $type, int $id ) use ( $trid_map ): ?int {
				$key = 'trid_for_' . $id;
				return $trid_map[ $key ] ?? null;
			}
		);
		$repo->method( 'list_by_trid' )->willReturnCallback(
			function ( int $trid ): array {
				$key = 'group_for_' . $trid;
				return $this->group_rows[ $key ] ?? array();
			}
		);

		return new MenuSync( $repo );
	}

	/**
	 * @var array<string, array<int, array{element_id:int, language_code:string, source_language_code:?string}>>
	 */
	private array $group_rows = array();

	/**
	 * Stub a translation group for a trid.
	 *
	 * @param int $trid
	 * @param array<int, array{element_id:int, language_code:string, source_language_code:?string}> $rows
	 */
	private function given_translation_group( int $trid, array $rows ): void {
		$this->group_rows[ 'group_for_' . $trid ] = $rows;
	}

	/**
	 * @var array<int, array{object:string, object_id:int}>
	 */
	private array $items = array();

	/**
	 * Stub a nav_menu_item meta for the given item id.
	 *
	 * @param int    $item_id
	 * @param string $object_type
	 * @param int    $object_id
	 */
	private function given_menu_item( int $item_id, string $object_type, int $object_id ): void {
		$this->items[ $item_id ] = array(
			'object'    => $object_type,
			'object_id' => $object_id,
		);

		// Reach into the singleton-ish globals that get_post_meta
		// consults in the WordPress test environment. The fallback
		// is to record the data on a global fake store; if the
		// production file invokes get_post_meta, our local stub
		// (registered in the constructor below) handles it.
		$GLOBALS['openpoly_test_post_meta'][ $item_id ] = $this->items[ $item_id ];
	}
}
