<?php
/**
 * NavMenu translation synchroniser.
 *
 * Given a source menu (typically the default-language menu) and a
 * target language, build a parallel menu in that language by
 * reading each nav_menu_item, looking up the post it points to in
 * op_translations, and writing a new nav_menu_item pointing at the
 * translated post. Items whose target post has no translation in
 * the target language fall back to the source post.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\NavMenu;

use OpenPoly\Translation\TranslationGroup;
use OpenPoly\Translation\Repository;
use OpenPoly\Url\UrlRouter;

defined( 'ABSPATH' ) || exit;

/**
 * Builds a translated copy of a WordPress nav menu.
 *
 * @since 0.5.0-dev
 */
final class MenuSync {

	/**
	 * Element type used for nav_menu_item in op_translations.
	 *
	 * @var string
	 */
	public const ELEMENT_TYPE = 'nav_menu_item';

	/**
	 * Translation repository used to look up trids.
	 *
	 * @var Repository
	 */
	private Repository $translations;

	/**
	 * Construct the menu synchroniser.
	 *
	 * @param Repository $translations Translation repository for trid lookups.
	 */
	public function __construct( Repository $translations ) {
		$this->translations = $translations;
	}

	/**
	 * Return the list of nav_menu_item ids belonging to a menu, in order.
	 *
	 * @param int $menu_id Source menu id.
	 * @return array<int, int>
	 */
	public function list_menu_items( int $menu_id ): array {
		$items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'publish' ) );
		if ( ! is_array( $items ) ) {
			return array();
		}
		$out = array();
		foreach ( $items as $item ) {
			if ( $item instanceof \WP_Post ) {
				$out[] = (int) $item->ID;
			}
		}
		return $out;
	}

	/**
	 * Look up a menu_item's target post id and resolve its translation
	 * in the given target language, falling back to the source post.
	 *
	 * @param int    $menu_item_id Nav menu item id.
	 * @param string $target_lang  Target language code.
	 * @return int|null Target post id (translated or source), or null when the item is not a post link.
	 */
	public function resolve_target_post( int $menu_item_id, string $target_lang ): ?int {
		$source_post_id = (int) get_post_meta( $menu_item_id, '_menu_item_object_id', true );
		if ( $source_post_id <= 0 ) {
			return null;
		}

		$object_type  = (string) get_post_meta( $menu_item_id, '_menu_item_object', true );
		$element_type = 'post_' . ( '' !== $object_type ? $object_type : 'post' );

		$trid = $this->translations->get_trid( $element_type, $source_post_id );
		if ( null === $trid ) {
			return $source_post_id;
		}

		$group = TranslationGroup::load( $trid, $this->translations );
		if ( null === $group ) {
			return $source_post_id;
		}

		$target_id = $group->get( $target_lang );
		return null !== $target_id ? (int) $target_id : $source_post_id;
	}

	/**
	 * Return the menu title for a nav menu, or '' when missing.
	 *
	 * @param int $menu_id Menu id.
	 * @return string Menu title or empty string when the menu does not exist.
	 */
	public function menu_title( int $menu_id ): string {
		$term = wp_get_nav_menu_object( $menu_id );
		if ( $term instanceof \WP_Term ) {
			return (string) $term->name;
		}
		return '';
	}

	/**
	 * Build a sync plan: for each nav_menu_item in the source menu,
	 * which target post it should point at in the target language.
	 *
	 * Public so tests can drive the logic without a real DB.
	 *
	 * @param int    $source_menu_id Source menu id.
	 * @param string $target_lang    Target language code.
	 * @return array<int, array{source_item:int, target_post:?int, source_post:?int}>
	 */
	public function build_sync_plan( int $source_menu_id, string $target_lang ): array {
		$plan  = array();
		$items = $this->list_menu_items( $source_menu_id );
		foreach ( $items as $item_id ) {
			$source_post = (int) get_post_meta( $item_id, '_menu_item_object_id', true );
			$plan[]      = array(
				'source_item' => $item_id,
				'target_post' => $this->resolve_target_post( $item_id, $target_lang ),
				'source_post' => $source_post > 0 ? $source_post : null,
			);
		}
		return $plan;
	}
}
