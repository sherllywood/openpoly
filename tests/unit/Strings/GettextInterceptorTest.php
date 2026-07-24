<?php
/**
 * Test: GettextInterceptor cache + translate.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Strings;

use OpenPoly\Language\LanguageManager;
use OpenPoly\Strings\GettextInterceptor;
use OpenPoly\Strings\StringRepository;
use OpenPoly\Url\UrlRouter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Strings\GettextInterceptor
 */
final class GettextInterceptorTest extends TestCase {

	public function testTranslateReturnsCachedWhenMissing(): void {
		$repo   = $this->createMock( StringRepository::class );
		$router = $this->make_router( 'en_US' );
		$inter  = new GettextInterceptor( $repo, $this->make_languages(), $router );

		$result = $inter->translate( 'default', 'Hello', 'test_domain' );

		self::assertSame( 'default', $result, 'Without a translation, original must be returned unchanged.' );
	}

	public function testTranslateReturnsTranslationWhenFound(): void {
		$repo = $this->createMock( StringRepository::class );
		$repo->method( 'load_translations' )->willReturnCallback(
			static function ( string $domain, string $lang ): array {
				if ( 'test_domain' === $domain && 'en_US' === $lang ) {
					return array(
						md5( 'test_domain||Hello' ) => 'Hola',
					);
				}
				return array();
			}
		);

		$router = $this->make_router( 'en_US' );
		$inter  = new GettextInterceptor( $repo, $this->make_languages(), $router );

		$result = $inter->translate( 'default', 'Hello', 'test_domain' );

		self::assertSame( 'Hola', $result );
	}

	public function testTranslateReturnsOriginalWhenNoLanguage(): void {
		$repo   = $this->createMock( StringRepository::class );
		$router = $this->make_router( null );
		$inter  = new GettextInterceptor( $repo, $this->make_languages(), $router );

		$result = $inter->translate( 'default', 'Hello', 'domain' );

		self::assertSame( 'default', $result );
	}

	public function testTranslateWithContextUsesMd5WithContext(): void {
		$repo = $this->createMock( StringRepository::class );
		$repo->method( 'load_translations' )->willReturnCallback(
			static function ( string $domain, string $lang ): array {
				if ( 'ctx_domain' === $domain && 'fr_FR' === $lang ) {
					return array(
						md5( 'ctx_domain|noun|Post' ) => 'Publication',
					);
				}
				return array();
			}
		);

		$router = $this->make_router( 'fr_FR' );
		$inter  = new GettextInterceptor( $repo, $this->make_languages(), $router );

		$result = $inter->translate_with_context( 'default', 'Post', 'noun', 'ctx_domain' );

		self::assertSame( 'Publication', $result );
	}

	/**
	 * Build a UrlRouter mock with the given current language.
	 *
	 * @param string|null $current
	 * @return UrlRouter
	 */
	private function make_router( ?string $current ): UrlRouter {
		$router = $this->createMock( UrlRouter::class );
		$router->method( 'current_language' )->willReturn( $current );
		return $router;
	}

	/**
	 * Build a LanguageManager mock.
	 *
	 * @return LanguageManager
	 */
	private function make_languages(): LanguageManager {
		return $this->createMock( LanguageManager::class );
	}
}
