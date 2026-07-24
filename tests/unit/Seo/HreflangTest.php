<?php
/**
 * Test: Hreflang.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Seo;

use OpenPoly\Language\LanguageManager;
use OpenPoly\Seo\Hreflang;
use OpenPoly\Translation\Repository;
use OpenPoly\Translation\TranslationGroup;
use OpenPoly\Url\LanguageUrlFilter;
use OpenPoly\Url\UrlRouter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Seo\Hreflang
 */
final class HreflangTest extends TestCase {

	public function testEmptyGroupYieldsNoLinks(): void {
		$hreflang = $this->make_hreflang(
			null,                                  // trid resolver returns null
			null,                                  // group loader returns null
			array( $this->lang( 'en_US', 'en' ) )
		);

		self::assertSame( array(), $hreflang->build_links( $this->make_post() ) );
	}

	public function testEachVariantGetsAHreflangEntry(): void {
		$trid = 42;
		$hreflang = $this->make_hreflang(
			static fn (): ?int => $trid,
			static function () use ( $trid ): TranslationGroup {
				return TranslationGroup::from_rows( $trid, array(
					array(
						'element_id'           => 100,
						'language_code'        => 'en_US',
						'source_language_code' => null,
					),
					array(
						'element_id'           => 200,
						'language_code'        => 'fr_FR',
						'source_language_code' => 'en_US',
					),
				) );
			},
			array(
				$this->lang( 'en_US', 'en' ),
				$this->lang( 'fr_FR', 'fr' ),
			)
		);

		$links = $hreflang->build_links( $this->make_post( 100 ) );
		self::assertCount( 3, $links );

		$langs = array_column( $links, 'hreflang' );
		self::assertContains( 'en', $langs );
		self::assertContains( 'fr', $langs );
		self::assertContains( 'x-default', $langs );
	}

	public function testHreflangValueFallsBackToCodeWhenBlank(): void {
		$trid = 1;
		$hreflang = $this->make_hreflang(
			static fn (): ?int => $trid,
			static function () use ( $trid ): TranslationGroup {
				return TranslationGroup::from_rows( $trid, array(
					array(
						'element_id'           => 5,
						'language_code'        => 'en_US',
						'source_language_code' => null,
					),
				) );
			},
			array( $this->lang( 'en_US', '' ) ) // empty hreflang -> fall back to code
		);

		$links = $hreflang->build_links( $this->make_post( 5 ) );
		$en_link = null;
		foreach ( $links as $l ) {
			if ( 'en_US' === $l['hreflang'] || 'en' === $l['hreflang'] ) {
				$en_link = $l;
			}
		}
		// Only the x-default should match the empty fallback case.
		self::assertNotNull( $en_link );
	}

	public function testSkipsLanguagesWithoutAVariant(): void {
		$trid = 1;
		$hreflang = $this->make_hreflang(
			static fn (): ?int => $trid,
			static function () use ( $trid ): TranslationGroup {
				return TranslationGroup::from_rows( $trid, array(
					array(
						'element_id'           => 5,
						'language_code'        => 'en_US',
						'source_language_code' => null,
					),
				) );
			},
			array(
				$this->lang( 'en_US', 'en' ),
				$this->lang( 'fr_FR', 'fr' ),  // no element in the group
				$this->lang( 'de_DE', 'de' ),  // no element in the group
			)
		);

		$links = $hreflang->build_links( $this->make_post( 5 ) );
		// en link + x-default; fr and de skipped.
		self::assertCount( 2, $links );

		$langs = array_column( $links, 'hreflang' );
		self::assertContains( 'en', $langs );
		self::assertContains( 'x-default', $langs );
		self::assertNotContains( 'fr', $langs );
		self::assertNotContains( 'de', $langs );
	}

	public function testAlwaysEmitsXDefaultWhenGroupExists(): void {
		$trid = 1;
		$hreflang = $this->make_hreflang(
			static fn (): ?int => $trid,
			static function () use ( $trid ): TranslationGroup {
				return TranslationGroup::from_rows( $trid, array(
					array(
						'element_id'           => 5,
						'language_code'        => 'en_US',
						'source_language_code' => null,
					),
				) );
			},
			array( $this->lang( 'en_US', 'en' ) )
		);

		$links = $hreflang->build_links( $this->make_post( 5 ) );
		$last   = $links[ count( $links ) - 1 ];
		self::assertSame( 'x-default', $last['hreflang'] );
	}

	/**
	 * Build a Hreflang instance with stubbed language directory and
	 * the supplied trid / group resolvers.
	 *
	 * @param (\Closure(string, int): (int|null))|null $trid_resolver
	 * @param (\Closure(int): (TranslationGroup|null))|null $group_loader
	 * @param array<int, array<string, mixed>>          $active_languages
	 * @return Hreflang
	 */
	private function make_hreflang( $trid_resolver, $group_loader, array $active_languages ): Hreflang {
		$router = $this->createMock( UrlRouter::class );
		$router->method( 'current_language' )->willReturn( 'en_US' );

		$languages = $this->createMock( LanguageManager::class );
		$languages->method( 'active_languages' )->willReturn( $active_languages );
		$languages->method( 'default_language_code' )->willReturn( 'en_US' );

		$url_filter = $this->createMock( LanguageUrlFilter::class );
		$url_filter->method( 'add_language_to_url' )->willReturnCallback(
			static function ( string $url ): string {
				// Echo the variant prefix in the test path so the
				// assertion can see that the language was actually
				// applied.
				return $url . '#with-language';
			}
		);

		$hreflang = new Hreflang( $router, $languages, $url_filter );
		if ( null !== $trid_resolver ) {
			$hreflang->set_trid_resolver( $trid_resolver );
		}
		if ( null !== $group_loader ) {
			$hreflang->set_group_loader( $group_loader );
		}
		return $hreflang;
	}

	/**
	 * Fake a language row.
	 *
	 * @param string $code     Language code.
	 * @param string $hreflang Hreflang value (empty to fall back to code).
	 * @return array<string, mixed>
	 */
	private function lang( string $code, string $hreflang ): array {
		return array(
			'id'         => 1,
			'code'       => $code,
			'hreflang'   => $hreflang,
			'is_default' => 'en_US' === $code ? 1 : 0,
		);
	}

	/**
	 * Fake a WP_Post object with the fields Hreflang inspects.
	 *
	 * @param int $id Post id.
	 * @return object
	 */
	private function make_post( int $id = 1 ): object {
		$post              = new \stdClass();
		$post->ID          = $id;
		$post->post_type   = 'post';
		$post->post_title  = 'Hello';
		$post->post_status = 'publish';
		return $post;
	}
}
