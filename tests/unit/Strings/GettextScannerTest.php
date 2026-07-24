<?php
/**
 * Test: GettextScanner.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Strings;

use OpenPoly\Strings\GettextScanner;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Strings\GettextScanner
 */
final class GettextScannerTest extends TestCase {

	private GettextScanner $scanner;

	protected function setUp(): void {
		parent::setUp();
		$this->scanner = new GettextScanner();
	}

	public function testDetectsSimpleGettext(): void {
		$code = '<?php echo __("Hello", "domain"); ?>';
		$res  = $this->scanner->parse( $code );

		self::assertCount( 1, $res );
		self::assertSame( 'Hello', $res[0]['text'] );
		self::assertSame( 'domain', $res[0]['domain'] );
		self::assertSame( '__', $res[0]['fn'] );
	}

	public function testDetectsEcho(): void {
		$code = '<?php _e("Save", "theme"); ?>';
		$res  = $this->scanner->parse( $code );

		self::assertCount( 1, $res );
		self::assertSame( 'Save', $res[0]['text'] );
		self::assertSame( '_e', $res[0]['fn'] );
	}

	public function testDetectsContextualGettext(): void {
		$code = '<?php _x("Post", "noun", "domain"); ?>';
		$res  = $this->scanner->parse( $code );

		self::assertCount( 1, $res );
		self::assertSame( 'Post', $res[0]['text'] );
		self::assertSame( 'noun', $res[0]['context'] );
	}

	public function testDetectsPluralForm(): void {
		$code = '<?php _n("Item", "Items", 3, "domain"); ?>';
		$res  = $this->scanner->parse( $code );

		self::assertCount( 1, $res );
		self::assertSame( 'Item', $res[0]['text'] );
		self::assertSame( 'Items', $res[0]['plural'] );
	}

	public function testIgnoresNonGettextCode(): void {
		$code = '<?php echo "Hello"; $x = some_function(); ?>';
		$res  = $this->scanner->parse( $code );

		self::assertCount( 0, $res );
	}

	public function testDefaultsDomainToDefault(): void {
		$code = '<?php __("Hello"); ?>';
		$res  = $this->scanner->parse( $code );

		self::assertSame( 'default', $res[0]['domain'] );
	}

	public function testEmptyStringReturnsEmpty(): void {
		$res = $this->scanner->parse( '' );
		self::assertCount( 0, $res );
	}

	public function testNonExistentFileReturnsEmpty(): void {
		$res = $this->scanner->scan_file( '/nonexistent/file.php' );
		self::assertCount( 0, $res );
	}

	public function testNonExistentDirectoryReturnsEmpty(): void {
		$res = $this->scanner->scan_directory( '/nonexistent' );
		self::assertCount( 0, $res );
	}
}
