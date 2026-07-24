<?php
/**
 * Test: Switcher.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Switcher;

use OpenPoly\Language\LanguageManager;
use OpenPoly\Switcher\Switcher;
use OpenPoly\Url\LanguageUrlFilter;
use OpenPoly\Url\UrlRouter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Switcher\Switcher
 */
final class SwitcherTest extends TestCase {

	public function testEmptyLanguageListRendersEmptyList(): void {
		$switcher = $this->make_switcher( 'en_US', array() );

		$out = $switcher->render();
		self::assertSame( '<ul class="openpoly-switcher"></ul>', $out );
	}

	public function testListLayoutRendersAllLanguages(): void {
		$switcher = $this->make_switcher(
			'en_US',
			array(
				$this->lang( 'en_US', 'English', '🇺🇸' ),
				$this->lang( 'fr_FR', 'Français', '🇫🇷' ),
			)
		);

		$out = $switcher->render( Switcher::LAYOUT_LIST );
		self::assertStringContainsString( '<ul class="openpoly-switcher">', $out );
		self::assertStringContainsString( '🇺🇸 English', $out );
		self::assertStringContainsString( '🇫🇷 Français', $out );
		self::assertStringContainsString( '</ul>', $out );
	}

	public function testCurrentLanguageIsMarkedWithAria(): void {
		$switcher = $this->make_switcher(
			'fr_FR',
			array(
				$this->lang( 'en_US', 'English', '🇺🇸' ),
				$this->lang( 'fr_FR', 'Français', '🇫🇷' ),
			)
		);

		$out = $switcher->render( Switcher::LAYOUT_LIST );
		// The fr_FR entry should be marked as current.
		self::assertStringContainsString( 'aria-current="true"', $out );
	}

	public function testInlineLayoutUsesSpanSeparators(): void {
		$switcher = $this->make_switcher(
			'en_US',
			array(
				$this->lang( 'en_US', 'English', '🇺🇸' ),
				$this->lang( 'fr_FR', 'Français', '🇫🇷' ),
			)
		);

		$out = $switcher->render( Switcher::LAYOUT_INLINE );
		self::assertStringContainsString( '<span class="openpoly-switcher-inline">', $out );
		self::assertStringContainsString( ' | ', $out );
		self::assertStringContainsString( '</span>', $out );
	}

	public function testDropdownLayoutRendersSelect(): void {
		$switcher = $this->make_switcher(
			'en_US',
			array(
				$this->lang( 'en_US', 'English', '🇺🇸' ),
				$this->lang( 'fr_FR', 'Français', '🇫🇷' ),
			)
		);

		$out = $switcher->render( Switcher::LAYOUT_DROPDOWN );
		self::assertStringContainsString( '<select class="openpoly-switcher-dropdown"', $out );
		self::assertStringContainsString( 'onchange="window.location.href=this.value"', $out );
		self::assertStringContainsString( '<option ', $out );
		self::assertStringContainsString( 'selected', $out );
	}

	public function testFlagsCanBeHidden(): void {
		$switcher = $this->make_switcher(
			'en_US',
			array(
				$this->lang( 'en_US', 'English', '🇺🇸' ),
			)
		);

		$out = $switcher->render( Switcher::LAYOUT_LIST, false );
		self::assertStringNotContainsString( '🇺🇸', $out );
		self::assertStringContainsString( 'English', $out );
	}

	public function testUnknownLayoutFallsBackToList(): void {
		$switcher = $this->make_switcher(
			'en_US',
			array( $this->lang( 'en_US', 'English', '🇺🇸' ) )
		);

		$out = $switcher->render( 'bogus-layout', true );
		self::assertStringStartsWith( '<ul class="openpoly-switcher">', $out );
	}

	public function testClassConstantsAreStable(): void {
		self::assertSame( 'list', Switcher::LAYOUT_LIST );
		self::assertSame( 'dropdown', Switcher::LAYOUT_DROPDOWN );
		self::assertSame( 'inline', Switcher::LAYOUT_INLINE );
	}

	/**
	 * Build a Switcher with a fixed current language and active list.
	 *
	 * @param string                $current
	 * @param array<int, array<string, mixed>> $active_languages
	 * @return Switcher
	 */
	private function make_switcher( string $current, array $active_languages ): Switcher {
		$router = $this->createMock( UrlRouter::class );
		$router->method( 'current_language' )->willReturn( $current );
		$router->method( 'set_current_language' )->willReturnCallback(
			function ( string $code ) {
				// Use reflection to actually update the property so
				// that the URL filter sees the right code.
				$ref = new \ReflectionClass( $this->router );
				$p   = $ref->getProperty( 'current_language' );
				$p->setAccessible( true );
				$p->setValue( $this->router, $code );
			}
		);

		$languages = $this->createMock( LanguageManager::class );
		$languages->method( 'active_languages' )->willReturn( $active_languages );

		$url_filter = $this->createMock( LanguageUrlFilter::class );
		$url_filter->method( 'add_language_to_url' )->willReturnArgument( 0 );

		return new Switcher( $router, $languages, $url_filter );
	}

	/**
	 * Fake a language row.
	 *
	 * @param string $code     Language code.
	 * @param string $native   Native name.
	 * @param string $flag     Flag emoji.
	 * @return array<string, mixed>
	 */
	private function lang( string $code, string $native, string $flag ): array {
		return array(
			'id'          => 1,
			'code'        => $code,
			'native_name' => $native,
			'flag'        => $flag,
		);
	}
}
