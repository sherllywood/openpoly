<?php
/**
 * Test: LanguageMetaBox HTML builder.
 *
 * The meta box render() method writes directly to output, so
 * tests call a private builder that returns the HTML string. This
 * keeps the test free of WP_Hook machinery.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Admin;

use OpenPoly\Admin\LanguageMetaBox;
use OpenPoly\Language\LanguageManager;
use OpenPoly\Translation\Repository;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Admin\LanguageMetaBox
 */
final class LanguageMetaBoxTest extends TestCase {

	public function testCreateUrlBuilderEncodesAllParameters(): void {
		$metabox = $this->make_metabox();

		$url = $metabox->build_create_url_public( 42, 'en_US' );

		// action present
		self::assertStringContainsString( 'action=openpoly_create_translation', $url );
		// post id present
		self::assertStringContainsString( 'post_id=42', $url );
		// language present
		self::assertStringContainsString( 'lang=en_US', $url );
		// nonce present
		self::assertMatchesRegularExpression( '/_wpnonce=[a-f0-9]+/', $url, 'URL must include a valid nonce token.' );
	}

	public function testCreateUrlEncodesActionConstant(): void {
		$metabox = $this->make_metabox();

		$url = $metabox->build_create_url_public( 1, 'fr_FR' );

		self::assertStringContainsString( 'action=' . LanguageMetaBox::class . '::NONCE_ACTION', $url, 'placeholder assertion' );
		self::assertStringContainsString( LanguageMetaBox::class . '::ACTION', $url, 'placeholder' );
	}

	public function testIdConstantIsStable(): void {
		self::assertSame( 'openpoly-language-metabox', LanguageMetaBox::ID );
		self::assertSame( 'openpoly_create_translation', LanguageMetaBox::NONCE_ACTION );
	}

	/**
	 * Build a LanguageMetaBox with mocked dependencies.
	 *
	 * @return LanguageMetaBox
	 */
	private function make_metabox(): LanguageMetaBox {
		$languages = $this->createMock( LanguageManager::class );
		$languages->method( 'active_languages' )->willReturn( array() );

		$repo = $this->createMock( Repository::class );

		$metabox = new LanguageMetaBox( $languages, $repo );

		// Expose a public wrapper for build_create_url.
		$ref = new \ReflectionClass( $metabox );
		$m   = $ref->getMethod( 'build_create_url' );
		$m->setAccessible( true );
		$wrapper = function ( int $post_id, string $code ) use ( $m, $metabox ): string {
			return (string) $m->invoke( $metabox, $post_id, $code );
		};
		// Attach the wrapper as a public method for test access.
		$public = new class( $metabox, $wrapper ) extends LanguageMetaBox {
			public function __construct(
				private LanguageMetaBox $inner,
				private $wrapper,
			) {}
			public function build_create_url_public( int $post_id, string $code ): string {
				return ( $this->wrapper )( $post_id, $code );
			}
		};
		return $public;
	}
}
