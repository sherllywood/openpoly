<?php
/**
 * Test: ContextResolver.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Url;

use OpenPoly\Language\LanguageManager;
use OpenPoly\Translation\Repository;
use OpenPoly\Url\ContextResolver;
use OpenPoly\Url\UrlRouter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Url\ContextResolver
 */
final class ContextResolverTest extends TestCase {

	/**
	 * @var array<string, mixed>
	 */
	private array $backup = array();

	protected function setUp(): void {
		parent::setUp();
		$this->backup = array(
			'GET'    => $_GET,
			'COOKIE' => $_COOKIE,
			'SERVER' => $_SERVER,
		);
	}

	protected function tearDown(): void {
		$_GET    = $this->backup['GET'];
		$_COOKIE = $this->backup['COOKIE'];
		$_SERVER = $this->backup['SERVER'];
		parent::tearDown();
	}

	public function testQueryOverridesEverything(): void {
		$resolver = $this->make_resolver( 'en_US' );
		$_GET[ UrlRouter::LANG_QUERY_VAR ] = 'fr_FR';

		self::assertSame( 'fr_FR', $resolver->resolve() );
	}

	public function testCookieUsedWhenNoQuery(): void {
		$resolver = $this->make_resolver( 'en_US' );
		$_COOKIE[ ContextResolver::COOKIE_NAME ] = 'de_DE';

		self::assertSame( 'de_DE', $resolver->resolve() );
	}

	public function testReferrerParsedWhenNoQueryOrCookie(): void {
		$resolver = $this->make_resolver( 'en_US' );
		$_SERVER['HTTP_REFERER'] = 'https://example.com/zh-hans/hello/';

		self::assertSame( 'zh-hans', $resolver->resolve() );
	}

	public function testFallsBackToRouterCurrent(): void {
		$resolver = $this->make_resolver( 'en_US' );

		// No query, no cookie, no referrer -> router's current wins.
		// We set it via reflection because current_language is private.
		$this->set_router_current( $resolver, 'ja' );

		self::assertSame( 'ja', $resolver->resolve() );
	}

	public function testReturnsNullWhenNoSourceMatches(): void {
		$resolver = $this->make_resolver( 'en_US' );
		// All sources are empty, router has no current.
		$this->set_router_current( $resolver, null );

		self::assertNull( $resolver->resolve() );
	}

	public function testIgnoresUnknownLanguageCode(): void {
		$resolver = $this->make_resolver( 'en_US' );
		$_GET[ UrlRouter::LANG_QUERY_VAR ] = 'xx_XX';
		// No router fallback.
		$this->set_router_current( $resolver, null );

		self::assertNull( $resolver->resolve() );
	}

	public function testQueryWinsOverCookie(): void {
		$resolver = $this->make_resolver( 'en_US' );
		$_GET[ UrlRouter::LANG_QUERY_VAR ]         = 'fr_FR';
		$_COOKIE[ ContextResolver::COOKIE_NAME ]  = 'de_DE';

		// Higher priority (query) wins.
		self::assertSame( 'fr_FR', $resolver->resolve() );
	}

	public function testCookieWinsOverReferrer(): void {
		$resolver = $this->make_resolver( 'en_US' );
		$_COOKIE[ ContextResolver::COOKIE_NAME ]   = 'de_DE';
		$_SERVER['HTTP_REFERER']                 = 'https://example.com/fr-fr/hello/';

		self::assertSame( 'de_DE', $resolver->resolve() );
	}

	public function testEmptyCookieYieldsNull(): void {
		$resolver = $this->make_resolver( 'en_US' );
		$_COOKIE[ ContextResolver::COOKIE_NAME ] = '';

		self::assertNull( $resolver->from_cookie() );
	}

	public function testEmptyReferrerYieldsNull(): void {
		$resolver = $this->make_resolver( 'en_US' );
		$_SERVER['HTTP_REFERER'] = '';

		self::assertNull( $resolver->from_referrer() );
	}

	/**
	 * Build a resolver with a fixed active-language list.
	 *
	 * @param string $active One active language code.
	 * @return ContextResolver
	 */
	private function make_resolver( string $active ): ContextResolver {
		$router = $this->createMock( UrlRouter::class );
		$router->method( 'match_path' )->willReturnCallback(
			static function ( string $path ) {
				$path = trim( $path, '/' );
				if ( '' === $path ) {
					return null;
				}
				$first = strtolower( strtok( $path, '/' ) );
				return str_replace( '_', '-', $first );
			}
		);

		$languages = $this->createMock( LanguageManager::class );
		$languages->method( 'active_languages' )->willReturn(
			array( array( 'id' => 1, 'code' => $active, 'is_active' => 1 ) )
		);

		$translations = $this->createMock( Repository::class );
		return new ContextResolver( $router, $languages, $translations );
	}

	/**
	 * Set the router's current language via reflection.
	 *
	 * @param ContextResolver $resolver
	 * @param string|null     $code
	 * @return void
	 */
	private function set_router_current( ContextResolver $resolver, ?string $code ): void {
		$reflection = new \ReflectionClass( $resolver );
		$router_ref = $reflection->getProperty( 'router' );
		$router_ref->setAccessible( true );
		$router    = $router_ref->getValue( $resolver );

		$router_ref2 = new \ReflectionClass( $router );
		$prop        = $router_ref2->getProperty( 'current_language' );
		$prop->setAccessible( true );
		$prop->setValue( $router, $code );
	}
}
