<?php
/**
 * Create-translation endpoint.
 *
 * Handles `?action=openpoly_create_translation` on admin-post.php:
 *   - verifies nonce and capability
 *   - reads source post + target language
 *   - clones the post via wp_insert_post
 *   - writes a row to op_translations with a new trid (or extends
 *     an existing one if the source already has a trid)
 *   - redirects to the new post's edit screen
 *
 * M-06 ships the minimum: title prefix "[<lang>] " and post status
 * inherit. M-07+ will replace this with the full duplicate /
 * fallback strategy.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Admin;

use OpenPoly\Language\LanguageManager;
use OpenPoly\Translation\Repository;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the create-translation admin-post action.
 *
 * @since 0.5.0-dev
 */
final class CreateTranslation {

	/**
	 * Admin-post action name.
	 */
	public const ACTION = 'openpoly_create_translation';

	/**
	 * Language directory used to validate the target language code.
	 *
	 * @var LanguageManager
	 */
	private LanguageManager $languages;

	/**
	 * Translation repository for trid lookups + inserts.
	 *
	 * @var Repository
	 */
	private Repository $translations;

	/**
	 * Construct the endpoint handler.
	 *
	 * @param LanguageManager $languages    Language directory.
	 * @param Repository      $translations Translation repository.
	 */
	public function __construct( LanguageManager $languages, Repository $translations ) {
		$this->languages    = $languages;
		$this->translations = $translations;
	}

	/**
	 * Register the admin_post_* hook.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Handle the action.
	 *
	 * @return void
	 */
	public function handle(): void {
		// Capability + nonce checks. Failure paths redirect back to
		// the source post edit screen with an error query var.
		if ( ! current_user_can( 'edit_posts' ) ) {
			$this->redirect_back( 'capability' );
		}

		$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- dedicated nonce check below.
		$lang  = isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( (string) $_GET['lang'] ) ) : '';
		$nonce = isset( $_GET['_wpnonce'] ) ? (string) wp_unslash( $_GET['_wpnonce'] ) : '';

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
		if ( ! wp_verify_nonce( $nonce, LanguageMetaBox::NONCE_ACTION ) ) {
			$this->redirect_back( 'nonce', $post_id );
		}

		if ( $post_id <= 0 || '' === $lang ) {
			$this->redirect_back( 'params', $post_id );
		}

		$source = get_post( $post_id );
		if ( ! $source instanceof WP_Post ) {
			$this->redirect_back( 'missing', $post_id );
		}

		// Validate the language is active.
		$known = false;
		foreach ( $this->languages->active_languages() as $l ) {
			if ( (string) $l['code'] === $lang ) {
				$known = true;
				break;
			}
		}
		if ( ! $known ) {
			$this->redirect_back( 'unknown_lang', $post_id );
		}

		$new_id = $this->create_translation( $source, $lang );
		if ( $new_id <= 0 ) {
			$this->redirect_back( 'create_failed', $post_id );
		}

		// Redirect to the new translation's edit screen.
		$edit_url = get_edit_post_link( $new_id, 'raw' );
		if ( ! is_string( $edit_url ) || '' === $edit_url ) {
			$this->redirect_back( 'no_edit_url', $post_id );
		}
		wp_safe_redirect( $edit_url, 302 );
		exit;
	}

	/**
	 * Create a new translation post for the source.
	 *
	 * @param \WP_Post $source Source post.
	 * @param string   $lang   Target language code.
	 * @return int New post id, or 0 on failure.
	 */
	private function create_translation( WP_Post $source, string $lang ): int {
		$element_type = 'post_' . $source->post_type;

		// Determine trid: reuse the source's trid, or allocate a new
		// one if the source has no group yet. We pass the source's
		// id as element_id; Repository::add will allocate a fresh
		// trid on first call.
		$trid = $this->translations->get_trid( $element_type, (int) $source->ID );
		if ( null === $trid ) {
			$trid = $this->translations->add(
				null,
				$element_type,
				(int) $source->ID,
				$lang,
				null
			);
		}

		$new_id = wp_insert_post(
			array(
				'post_title'   => sprintf( '[%s] %s', $lang, $source->post_title ),
				'post_content' => $source->post_content,
				'post_excerpt' => $source->post_excerpt,
				'post_status'  => 'draft',
				'post_type'    => $source->post_type,
				'post_author'  => (int) $source->post_author,
				'post_parent'  => (int) $source->post_parent,
				'menu_order'   => (int) $source->menu_order,
			),
			true
		);
		if ( is_wp_error( $new_id ) ) {
			return 0;
		}
		$new_id = (int) $new_id;
		// Hard fail returned as 0 by wp_insert_post at runtime
		// even though the PHPDoc promises int<1, max>. The redundant
		// check is intentional defensive code.
		// @phpstan-ignore-next-line.
		if ( 0 === $new_id ) {
			return 0;
		}

		// Register the new post in the translation group.
		// If the source had no trid, the call above created one; the
		// new post now joins the same group.
		$this->translations->add(
			$trid,
			$element_type,
			$new_id,
			$lang,
			null
		);

		return $new_id;
	}

	/**
	 * Redirect back to the source edit screen with an error flag.
	 *
	 * @param string $reason  Error reason token for debug / display.
	 * @param int    $post_id Source post id.
	 * @return void
	 */
	private function redirect_back( string $reason, int $post_id = 0 ): void {
		$url = admin_url( 'edit.php?post_type=post' );
		if ( $post_id > 0 ) {
			$edit = get_edit_post_link( $post_id, 'raw' );
			if ( is_string( $edit ) && '' !== $edit ) {
				$url = $edit;
			}
		}
		$url = add_query_arg( 'openpoly_create_error', $reason, $url );
		wp_safe_redirect( $url, 302 );
		exit;
	}
}
