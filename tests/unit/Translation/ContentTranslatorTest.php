<?php
/**
 * Test: ContentTranslator.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Translation;

use OpenPoly\Translation\ContentMode;
use OpenPoly\Translation\ContentTranslator;
use OpenPoly\Translation\TranslationGroup;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Translation\ContentTranslator
 */
final class ContentTranslatorTest extends TestCase {

	private ContentTranslator $translator;

	protected function setUp(): void {
		parent::setUp();
		$this->translator = new ContentTranslator();
	}

	public function testReturnsTranslatedWhenTargetLanguageExists(): void {
		$group = TranslationGroup::from_rows(
			1,
			array(
				$this->row( 100, 'zh_CN', null ),
				$this->row( 200, 'en_US', 'zh_CN' ),
				$this->row( 300, 'fr_FR', 'zh_CN' ),
			)
		);

		$res = $this->translator->resolve( $group, 'en_US' );

		self::assertSame( ContentMode::TRANSLATED, $res->mode );
		self::assertSame( 200, $res->element_id );
		self::assertSame( 'en_US', $res->language_code );
	}

	public function testReturnsFallbackWhenTargetLanguageMissing(): void {
		$group = TranslationGroup::from_rows(
			1,
			array(
				$this->row( 100, 'zh_CN', null ),
				$this->row( 200, 'en_US', 'zh_CN' ),
			)
		);

		$res = $this->translator->resolve( $group, 'ja' );

		self::assertSame( ContentMode::FALLBACK, $res->mode );
		self::assertSame( 100, $res->element_id, 'Fallback should return the source element id.' );
		self::assertSame( 'ja', $res->language_code, 'Fallback should preserve the target language code.' );
	}

	public function testReturnsSourceWhenRequestingSourceLanguage(): void {
		$group = TranslationGroup::from_rows(
			1,
			array(
				$this->row( 100, 'zh_CN', null ),
				$this->row( 200, 'en_US', 'zh_CN' ),
			)
		);

		$res = $this->translator->resolve( $group, 'zh_CN' );

		self::assertSame( ContentMode::TRANSLATED, $res->mode );
		self::assertSame( 100, $res->element_id );
		self::assertSame( 'zh_CN', $res->language_code );
	}

	public function testReturnsFallbackElementIdNullWhenNoSource(): void {
		$group = TranslationGroup::from_rows(
			1,
			array(
				$this->row( 200, 'en_US', 'zh_CN' ),
			)
		);

		$res = $this->translator->resolve( $group, 'fr_FR' );

		self::assertSame( ContentMode::FALLBACK, $res->mode );
		self::assertNull( $res->element_id, 'Without a source, there is nothing to render.' );
		self::assertSame( 'fr_FR', $res->language_code );
	}

	public function testReturnsTranslatedForEmptyGroupLookup(): void {
		// Empty group is a no-op edge case; should never happen in
		// production (TranslationGroup::load returns null) but the
		// translator must not crash.
		$group = TranslationGroup::from_rows( 1, array() );

		$res = $this->translator->resolve( $group, 'en_US' );

		self::assertSame( ContentMode::FALLBACK, $res->mode );
		self::assertNull( $res->element_id );
	}

	public function testDuplicateModeIsADistinctCase(): void {
		// M-07 ships the FALLBACK default; the DUPLICATE upgrade is
		// the caller's responsibility (it knows about the shadow
		// post outside the translation group). The translator itself
		// does not promote FALLBACK to DUPLICATE.
		$group = TranslationGroup::from_rows(
			1,
			array(
				$this->row( 100, 'zh_CN', null ),
			)
		);

		$res = $this->translator->resolve( $group, 'en_US' );

		self::assertNotSame( ContentMode::DUPLICATE, $res->mode, 'Translator must not invent DUPLICATE without external context.' );
	}

	/**
	 * Build a fake element row.
	 *
	 * @param int         $element_id
	 * @param string      $language_code
	 * @param string|null $source_language_code
	 * @return array{element_id:int, language_code:string, source_language_code:?string}
	 */
	private function row( int $element_id, string $language_code, ?string $source_language_code ): array {
		return array(
			'element_id'           => $element_id,
			'language_code'        => $language_code,
			'source_language_code' => $source_language_code,
		);
	}
}
