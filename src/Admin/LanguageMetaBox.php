<?php
/**
 * Editor-side "Language" meta box.
 *
 * Renders one row per active language, showing whether a
 * translation already exists and linking to either the existing
 * translation (edit) or the create-translation endpoint (add).
 *
 * Pure HTML builder: it does not touch the database; the caller
 * passes in the translation map.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Admin;

use OpenPoly\Language\LanguageManager;
use OpenPoly\Translation\TranslationGroup;
use OpenPoly\Translation\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the "Language" meta box on post / page edit screens.
 *
 * @since 0.5.0-dev
 */
final class LanguageMetaBox {

	/**
	 * Meta box id.
	 */
	public const ID = 'openpoly-language-metabox';

	/**
	 * Nonce action for the create-translation endpoint.
	 */
	public const NONCE_ACTION = 'openpoly_create_translation';

	/**
	 * Language directory used to enumerate variants and read the default.
	 *
	 * @var LanguageManager
	 */
	private LanguageManager $languages;

	/**
	 * Translation repository for trid lookups.
	 *
	 * @var Repository
	 */
	private Repository $translations;

	/**
	 * Construct the meta box renderer.
	 *
	 * @param LanguageManager $languages    Language directory.
	 * @param Repository      $translations Translation repository.
	 */
	public function __construct( LanguageManager $languages, Repository $translations ) {
		$this->languages    = $languages;
		$this->translations = $translations;
	}

	/**
	 * Register the add_meta_boxes hook for post and page.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
	}

	/**
	 * Add_meta_boxes callback: register the meta box for post + page.
	 *
	 * @param string $post_type Current screen post type.
	 * @return void
	 */
	public function register_meta_box( string $post_type ): void {
		if ( ! in_array( $post_type, array( 'post', 'page' ), true ) ) {
			return;
		}
		add_meta_box(
			self::ID,
			__( 'Language', 'openpoly' ),
			array( $this, 'render' ),
			$post_type,
			'side',
			'high'
		);
	}

	/**
	 * Render the meta box.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render( $post ): void {
		$element_type = 'post_' . $post->post_type;
		$trid         = $this->translations->get_trid( $element_type, (int) $post->ID );

		$group  = null;
		$source = null;
		if ( null !== $trid ) {
			$group  = TranslationGroup::load( $trid, $this->translations );
			$source = null !== $group ? $group->source_language() : null;
		}

		echo '<div class="openpoly-language-metabox">';
		echo '<input type="hidden" name="openpoly_post_type" value="' . esc_attr( $post->post_type ) . '" />';

		$is_new = ( null === $trid );
		echo '<p><strong>' . esc_html( $is_new ? __( 'No translation group yet.', 'openpoly' ) : __( 'Translation group:', 'openpoly' ) ) . '</strong>';
		if ( ! $is_new ) {
			echo ' <code>#' . (int) $trid . '</code>';
			if ( null !== $source ) {
				echo ' ' . esc_html( sprintf( /* translators: %s: source language code */ __( '(source: %s)', 'openpoly' ), $source ) );
			}
		}
		echo '</p>';

		echo '<ul class="openpoly-language-list">';
		foreach ( $this->languages->active_languages() as $lang ) {
			$code           = (string) $lang['code'];
			$native         = (string) $lang['native_name'];
			$is_source_lang = null !== $source && $code === $source;

			$existing_id = null !== $group ? $group->get( $code ) : null;

			echo '<li>';
			echo '<span class="openpoly-lang-name">' . esc_html( $native ) . ' <code>' . esc_html( $code ) . '</code></span> ';

			if ( $is_source_lang ) {
				echo ' <em>(' . esc_html__( 'source', 'openpoly' ) . ')</em>';
			} elseif ( null !== $existing_id ) {
				$edit_url = get_edit_post_link( $existing_id, 'raw' );
				if ( is_string( $edit_url ) && '' !== $edit_url ) {
					echo ' <a class="button button-small" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'openpoly' ) . '</a>';
				}
			} else {
				echo ' <em>(' . esc_html__( 'no translation', 'openpoly' ) . ')</em> ';
				// Skip "add" for the source language itself.
				if ( ! $is_new ) {
					$create_url = $this->build_create_url( (int) $post->ID, $code );
					echo ' <a class="button button-primary button-small" href="' . esc_url( $create_url ) . '">' . esc_html__( 'Add translation', 'openpoly' ) . '</a>';
				}
			}
			echo '</li>';
		}
		echo '</ul></div>';
	}

	/**
	 * Build the admin-post URL that creates a translation.
	 *
	 * @param int    $post_id Source post id.
	 * @param string $code    Target language code.
	 * @return string Full admin-post URL with action, post id, language, and nonce.
	 */
	private function build_create_url( int $post_id, string $code ): string {
		$args = array(
			'action'   => 'openpoly_create_translation',
			'post_id'  => $post_id,
			'lang'     => $code,
			'_wpnonce' => wp_create_nonce( self::NONCE_ACTION ),
		);
		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}
}
