<?php
/**
 * Test: UrlRouter::match_path.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Url;

use OpenPoly\Language\LanguageManager;
use OpenPoly\Translation\Repository;
use OpenPoly\Url\UrlRouter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Url\UrlRouter
 */
final class UrlRouterTest extends TestCase {

	public function testMatchPathReturnsNullForEmptyPath(): void {
		$router = $this->make_router( array( $this->row( 1, 'en_US' ) ) );

		self::assertNull( $router->match_path( '/' ) );
		self::assertNull( $router->match_path( '' ) );
	}

	public function testMatchPathReturnsNullForUnknownLanguage(): void {
		$router = $this->make_router( array( $this->row( 1, 'en_US' ) ) );

		self::assertNull( $router->match_path( '/fr_FR/hello/' ) );
	}

	public function testMatchPathNormalisesUnderscoreToHyphen(): void {
		$router = $this->make_router( array( $this->row( 1, 'en_US' ) ) );

		self::assertSame( 'en-us', $router->match_path( '/en_US/hello-world/' ) );
	}

	public function testMatchPathIsCaseInsensitive(): void {
		$router = $this->make_router( array( $this->row( 1, 'en_US' ) ) );

		self::assertSame( 'en-us', $router->match_path( '/EN_US/hello/' ) );
	}

	public function testMatchPathReturnsNullForRootWithoutLanguage(): void {
		$router = $this->make_router( array( $this->row( 1, 'en_US' ) ) );

		self::assertNull( $router->match_path( '/hello-world/' ) );
	}

	public function testCurrentLanguageStartsNull(): void {
		$router = $this->make_router( array( $this->row( 1, 'en_US' ) ) );

		self::assertNull( $router->current_language() );
	}

	/**
	 * Build a router with a mocked LanguageManager.
	 *
	 * @param array<int, array<string, mixed>> $active_languages
	 * @return UrlRouter
	 */
	private function make_router( array $active_languages ): UrlRouter {
		$lang = $this->createMock( LanguageManager::class );
		$lang->method( 'active_languages' )->willReturn( $active_languages );
		$lang->method( 'default_language_code' )->willReturn( 'en_US' );

		$trans = $this->createMock( Repository::class );
		return new UrlRouter( $lang, $trans );
	}

	/**
	 * Build a fake language row.
	 *
	 * @param int    $id
	 * @param string $code
	 * @return array<string, mixed>
	 */
	private function row( int $id, string $code ): array {
		return array( 'id' => $id, 'code' => $code, 'is_active' => 1 );
	}
}
