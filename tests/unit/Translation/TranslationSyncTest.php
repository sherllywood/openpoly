<?php
/**
 * Test: TranslationSync.
 *
 * Verifies the save_post handler short-circuits on the conditions
 * listed in 02 architecture §3.3 (revision, autosave, running flag,
 * untracked post type) and that a real call computes the fingerprint
 * and forwards to StatusRepository::mark_stale.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Translation;

use OpenPoly\Translation\Repository;
use OpenPoly\Translation\StatusRepository;
use OpenPoly\Translation\TranslationSync;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Translation\TranslationSync
 */
final class TranslationSyncTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// Clear the static guard between tests.
		TranslationSync::$running = false;
	}

	public function testDoesNothingWhenGuardFlagIsSet(): void {
		$repo  = $this->createMock( Repository::class );
		$state = $this->createMock( StatusRepository::class );
		$repo->expects( self::never() )->method( 'get_trid' );
		$state->expects( self::never() )->method( 'mark_stale' );

		$sync = new TranslationSync( $repo, $state );
		TranslationSync::$running = true;
		$sync->on_save_post( 1, $this->make_post( 'post', 1, 'post' ) );

		TranslationSync::$running = false;
	}

	public function testDoesNothingWhenNotInATranslatablePostType(): void {
		$repo  = $this->createMock( Repository::class );
		$state = $this->createMock( StatusRepository::class );
		$repo->expects( self::never() )->method( 'get_trid' );
		$state->expects( self::never() )->method( 'mark_stale' );

		$sync = new TranslationSync( $repo, $state );
		$sync->on_save_post( 1, $this->make_post( 'attachment', 1, 'attachment' ) );
	}

	public function testDoesNothingWhenPostHasNoTrid(): void {
		$repo  = $this->createMock( Repository::class );
		$state = $this->createMock( StatusRepository::class );
		$repo->expects( self::once() )
			->method( 'get_trid' )
			->with( 'post_post', 42 )
			->willReturn( null );
		$state->expects( self::never() )->method( 'mark_stale' );

		$sync = new TranslationSync( $repo, $state );
		$sync->on_save_post( 42, $this->make_post( 'post', 42, 'post' ) );
	}

	public function testComputesFingerprintAndForwardsToStatus(): void {
		$repo  = $this->createMock( Repository::class );
		$state = $this->createMock( StatusRepository::class );

		$repo->expects( self::once() )
			->method( 'get_trid' )
			->with( 'post_post', 7 )
			->willReturn( 99 );

		$state->expects( self::once() )
			->method( 'mark_stale' )
			->with( 99, self::isType( 'string' ) );

		$sync = new TranslationSync( $repo, $state );
		$sync->on_save_post( 7, $this->make_post( 'post', 7, 'post' ) );
	}

	public function testHooksReturnsSavePostBinding(): void {
		$sync = new TranslationSync(
			$this->createMock( Repository::class ),
			$this->createMock( StatusRepository::class )
		);

		$hooks = iterator_to_array( $sync->hooks() );

		self::assertCount( 1, $hooks );
		self::assertSame( 'save_post', $hooks[0]->hook );
		self::assertSame( 'on_save_post', $hooks[0]->method );
		self::assertSame( 20, $hooks[0]->priority );
		self::assertFalse( $hooks[0]->is_filter );
	}

	/**
	 * Build a fake post with the fields TranslationSync reads.
	 *
	 * @param string $type
	 * @param int    $id
	 * @param string $content_marker
	 * @return object
	 */
	private function make_post( string $type, int $id, string $content_marker ): object {
		$post = new \stdClass();
		$post->ID            = $id;
		$post->post_type     = $type;
		$post->post_title    = 'Title ' . $content_marker;
		$post->post_content  = 'Body ' . $content_marker;
		$post->post_excerpt  = 'Excerpt';
		$post->post_thumbnail = 0;
		return $post;
	}
}
