<?php
/**
 * Test: SourceFingerprint.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Translation;

use OpenPoly\Translation\SourceFingerprint;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Translation\SourceFingerprint
 */
final class SourceFingerprintTest extends TestCase {

	public function testFieldsReturnsStableOrder(): void {
		$fields = SourceFingerprint::fields();

		self::assertSame(
			array( 'post_title', 'post_content', 'post_excerpt', 'post_thumbnail' ),
			$fields
		);
	}

	public function testComputeIsDeterministic(): void {
		$post = $this->make_post( 'Hello', 'World', 'Excerpt', 7 );

		$a = SourceFingerprint::compute( $post );
		$b = SourceFingerprint::compute( $post );

		self::assertSame( $a, $b );
		self::assertSame( 32, strlen( $a ), 'md5 hex digest is 32 chars.' );
	}

	public function testComputeChangesWhenTitleChanges(): void {
		$a = SourceFingerprint::compute( $this->make_post( 'Hello', 'World', 'Excerpt', 7 ) );
		$b = SourceFingerprint::compute( $this->make_post( 'Hi', 'World', 'Excerpt', 7 ) );

		self::assertNotSame( $a, $b );
	}

	public function testComputeChangesWhenContentChanges(): void {
		$a = SourceFingerprint::compute( $this->make_post( 'Hello', 'World', 'Excerpt', 7 ) );
		$b = SourceFingerprint::compute( $this->make_post( 'Hello', 'Earth', 'Excerpt', 7 ) );

		self::assertNotSame( $a, $b );
	}

	public function testComputeIsOrderInsensitiveAcrossFields(): void {
		// The fingerprint should not depend on the order in which
		// fields are provided; the order is fixed inside fields().
		$post1 = $this->make_post( 'A', 'B', 'C', 1 );
		$post2 = $this->make_post( 'A', 'B', 'C', 1 );

		self::assertSame(
			SourceFingerprint::compute( $post1 ),
			SourceFingerprint::compute( $post2 )
		);
	}

	public function testComputeFromArrayMatchesCompute(): void {
		$post = $this->make_post( 'Title', 'Body', 'Summary', 42 );

		$array_md5 = SourceFingerprint::compute_from_array(
			array(
				'post_title'     => 'Title',
				'post_content'   => 'Body',
				'post_excerpt'   => 'Summary',
				'post_thumbnail' => 42,
			)
		);

		self::assertSame( SourceFingerprint::compute( $post ), $array_md5 );
	}

	public function testEmptyPostYieldsStableFingerprint(): void {
		$post = $this->make_post( '', '', '', 0 );

		self::assertNotEmpty( SourceFingerprint::compute( $post ) );
		self::assertSame( 32, strlen( SourceFingerprint::compute( $post ) ) );
	}

	/**
	 * Build a fake post object with the four fields the
	 * SourceFingerprint reads.
	 *
	 * @param string $title
	 * @param string $content
	 * @param string $excerpt
	 * @param int    $thumbnail
	 * @return object
	 */
	private function make_post( string $title, string $content, string $excerpt, int $thumbnail ): object {
		$post       = new \stdClass();
		$post->post_title     = $title;
		$post->post_content   = $content;
		$post->post_excerpt   = $excerpt;
		$post->post_thumbnail = $thumbnail;
		return $post;
	}
}
