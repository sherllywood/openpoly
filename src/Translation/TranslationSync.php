<?php
/**
 * Hooks that keep the translation state in sync with post saves.
 *
 * - On save_post, recompute the source content fingerprint and
 *   mark any (trid, language) row whose stored md5 differs as
 *   needs_update (FR-CORE-006).
 * - Skips revisions, autosaves, and our own duplicate-sync writes
 *   to avoid feedback loops.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Translation;

use OpenPoly\Bootstrap\Hookable;
use OpenPoly\Bootstrap\HookDefinition;

defined( 'ABSPATH' ) || exit;

/**
 * Wires save_post to source-fingerprint diffing.
 *
 * @since 0.5.0-dev
 */
final class TranslationSync implements Hookable {

	/**
	 * Static guard flag: set while we are propagating a duplicate
	 * mirror, so the save_post handler does not re-fire on the
	 * shadow post and re-evaluate the fingerprint for the same
	 * source again. Cleared in shutdown after the save_post stack
	 * unwinds.
	 *
	 * @var bool
	 */
	public static bool $running = false;

	/**
	 * Group data-access object.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Status data-access object.
	 *
	 * @var StatusRepository
	 */
	private StatusRepository $status;

	/**
	 * Construct the sync handler.
	 *
	 * @param Repository       $repository Group data-access object.
	 * @param StatusRepository $status     Status data-access object.
	 */
	public function __construct( Repository $repository, StatusRepository $status ) {
		$this->repository = $repository;
		$this->status     = $status;
	}

	/**
	 * Hook declarations.
	 *
	 * @return iterable<HookDefinition>
	 */
	public function hooks(): iterable {
		return array(
			new HookDefinition( 'save_post', 'on_save_post', 20, 2, false ),
		);
	}

	/**
	 * Handle a save_post event.
	 *
	 * @param int      $post_id Saved post id.
	 * @param \WP_Post $post    Saved post object.
	 * @return void
	 */
	public function on_save_post( $post_id, $post ): void {
		if ( self::$running ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		// Only react to post types we translate (post + page by default).
		$translatable = array( 'post', 'page' );
		if ( ! in_array( $post->post_type, $translatable, true ) ) {
			return;
		}

		$trid = $this->repository->get_trid( 'post_' . $post->post_type, (int) $post->ID );
		if ( null === $trid ) {
			return;
		}

		$fingerprint = SourceFingerprint::compute( $post );
		$this->status->mark_stale( $trid, $fingerprint );
	}
}
