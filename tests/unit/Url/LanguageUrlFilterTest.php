<?php
/**
 * Test: LanguageUrlFilter::add_language_to_url.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Url;

use OpenPoly\Language\LanguageManager;
use OpenPoly\Url\LanguageUrlFilter;
use OpenPoly\Url\UrlRouter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Url\LanguageUrlFilter
 */
final class LanguageUrlFilterTest extends TestCase {

	public function testReturnsUnchangedWhenNoCurrentLanguage(): void {
		$filter = $this->make_filter( 'fr_FR', 'en_US', null );

		self::assertSame( 'https://example.com/hello/', $filter->add_language_to_url( 'https://example.com/hello/' ) );
	}

	public function testSkipsDefaultLanguage(): void {
		$filter = $this->make_filter( 'en_US', 'en_US', 'en_US' );

		self::assertSame( 'https://example.com/hello/', $filter->add_language_to_url( 'https://example.com/hello/' ) );
	}

	public function testPrependsLanguagePrefixToAbsoluteUrl(): void {
		$filter = $this->make_filter( 'en_US', 'fr_FR', 'fr_FR' );

		self::assertSame( 'https://example.com/fr-fr/hello/', $filter->add_language_to_url( 'https://example.com/hello/' ) );
	}

	public function testPrependsLanguagePrefixToRelativePath(): void {
		$filter = $this->make_filter( 'en_US', 'fr_FR', 'fr_FR' );

		self::assertSame( '/fr-fr/hello/', $filter->add_language_to_url( '/hello/' ) );
	}

	public function testDoesNotDoublePrependIfAlreadyHasLanguage(): void {
		$filter = $this->make_filter( 'en_US', 'fr_FR', 'fr_FR' );

		// Already prefixed URL passes through unchanged.
		$url = 'https://example.com/fr-fr/hello/';
		self::assertSame( $url, $filter->add_language_to_url( $url ) );
	}

	public function testPreservesQueryString(): void {
		$filter = $this->make_filter( 'en_US', 'fr_FR', 'fr_FR' );

		self::assertSame(
			'https://example.com/fr-fr/hello/?p=1',
			$filter->add_language_to_url( 'https://example.com/hello/?p=1' )
		);
	}

	public function testPreservesFragment(): void {
		$filter = $this->make_filter( 'en_US', 'fr_FR', 'fr_FR' );

		self::assertSame(
			'https://example.com/fr-fr/hello/#section',
			$filter->add_language_to_url( 'https://example.com/hello/#section' )
		);
	}

	/**
	 * Build a filter with the given language context.
	 *
	 * @param string      $active_code  An active language code in the manager.
	 * @param string      $default_code The default language code.
	 * @param string|null $current      The current request language (or null).
	 * @return LanguageUrlFilter
	 */
	private function make_filter( string $active_code, string $default_code, ?string $current ): LanguageUrlFilter {
		$lang = $this->createMock( LanguageManager::class );
		$lang->method( 'active_languages' )->willReturn(
			array( array( 'id' => 1, 'code' => $active_code, 'is_active' => 1 ) )
		);
		$lang->method( 'default_language_code' )->willReturn( $default_code );

		$router = $this->createMock( UrlRouter::class );
		$router->method( 'current_language' )->willReturn( $current );

		return new LanguageUrlFilter( $lang, $router );
	}
}
