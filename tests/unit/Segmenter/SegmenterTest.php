<?php
/**
 * Test: Segmenter paragraph and sentence splitting.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Segmenter;

use OpenPoly\Segmenter\Segmenter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Segmenter\Segmenter
 */
final class SegmenterTest extends TestCase {

	private Segmenter $segmenter;

	protected function setUp(): void {
		parent::setUp();
		$this->segmenter = new Segmenter();
	}

	public function testSplitParagraphsOnDoubleNewline(): void {
		$content = "Hello world.\n\nThis is paragraph two.";
		$paras   = $this->segmenter->split_paragraphs( $content );

		self::assertCount( 2, $paras );
		self::assertSame( 'Hello world.', $paras[0] );
		self::assertSame( 'This is paragraph two.', $paras[1] );
	}

	public function testSplitParagraphsWithHTML(): void {
		$content = "<p>Hello world.</p>\n\n<p>Second paragraph.</p>";
		$paras   = $this->segmenter->split_paragraphs( $content );

		self::assertCount( 2, $paras );
	}

	public function testSplitParagraphsEmptyContent(): void {
		$paras = $this->segmenter->split_paragraphs( '' );
		self::assertCount( 0, $paras );
	}

	public function testSplitSentencesSimple(): void {
		$text      = 'Hello world. This is a test. Is it working? Yes!';
		$sentences = $this->segmenter->split_sentences( $text );

		self::assertGreaterThanOrEqual( 4, count( $sentences ) );
	}

	public function testSplitSentencesPreservesAbbreviations(): void {
		$text      = 'Dr. Smith went to Washington. He arrived at 5 p.m.';
		$sentences = $this->segmenter->split_sentences( $text );

		// "Dr." should not split.
		self::assertCount( 2, $sentences );
		self::assertStringContainsString( 'Dr. Smith', $sentences[0] );
	}

	public function testSplitSentencesSingleText(): void {
		$text      = 'This is a simple sentence without punctuation';
		$sentences = $this->segmenter->split_sentences( $text );

		self::assertCount( 1, $sentences );
		self::assertSame( $text, $sentences[0] );
	}

	public function testSegmentFullContent(): void {
		$content = "Hello world.\n\nGoodbye world. See you later.";
		$segments = $this->segmenter->segment( $content );

		self::assertGreaterThan( 1, count( $segments ) );

		// Every segment must have required keys.
		foreach ( $segments as $seg ) {
			self::assertArrayHasKey( 'paragraph_index', $seg );
			self::assertArrayHasKey( 'segment_index', $seg );
			self::assertArrayHasKey( 'text', $seg );
			self::assertArrayHasKey( 'md5', $seg );
			self::assertNotEmpty( $seg['text'] );
		}
	}

	public function testSegmentEmptyContent(): void {
		$segments = $this->segmenter->segment( '' );
		self::assertCount( 0, $segments );
	}

	public function testSegmentWPCoreStyleContent(): void {
		$content = "<!-- wp:paragraph -->\n<p>First paragraph with <strong>bold</strong> text.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Second paragraph.</p>\n<!-- /wp:paragraph -->";
		$segments = $this->segmenter->segment( $content );

		// Should produce at least some segments even with HTML.
		self::assertGreaterThan( 0, count( $segments ) );
	}
}
