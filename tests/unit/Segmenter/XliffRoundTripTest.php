<?php
/**
 * Test: XliffExport round-trip and XliffImport.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Segmenter;

use OpenPoly\Segmenter\XliffExport;
use OpenPoly\Segmenter\XliffImport;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Segmenter\XliffExport
 * @covers \OpenPoly\Segmenter\XliffImport
 */
final class XliffRoundTripTest extends TestCase {

	private XliffExport $exporter;
	private XliffImport $importer;

	protected function setUp(): void {
		parent::setUp();
		$this->exporter = new XliffExport();
		$this->importer = new XliffImport();
	}

	public function testExportProducesValidXliff(): void {
		$segments = array(
			array(
				'id'              => 1,
				'segment_index'   => 0,
				'source_text'     => 'Hello world.',
				'translated_text' => '你好世界。',
			),
			array(
				'id'              => 2,
				'segment_index'   => 1,
				'source_text'     => 'Goodbye.',
				'translated_text' => '再见。',
			),
		);

		$xml = $this->exporter->export( 'en_US', 'zh_CN', $segments );

		self::assertNotEmpty( $xml );
		self::assertStringContainsString( 'xliff', $xml );
		self::assertStringContainsString( 'Hello world.', $xml );
		self::assertStringContainsString( '你好世界。', $xml );
	}

	public function testExportEmptySegments(): void {
		$xml = $this->exporter->export( 'en_US', 'zh_CN', array() );
		self::assertEmpty( $xml );
	}

	public function testExportUsesNormalizedLanguageCodes(): void {
		$segments = array(
			array(
				'id'              => 1,
				'segment_index'   => 0,
				'source_text'     => 'Test.',
				'translated_text' => '',
			),
		);

		$xml = $this->exporter->export( 'en_US', 'pt_BR', $segments );

		self::assertStringContainsString( 'srcLang="en-us"', $xml );
		self::assertStringContainsString( 'trgLang="pt-br"', $xml );
	}

	public function testImportParsesXliff(): void {
		$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.0" srcLang="en-us" trgLang="zh-cn">
  <file id="f1">
    <unit id="1">
      <segment>
        <source>Hello world.</source>
        <target>你好世界。</target>
      </segment>
    </unit>
    <unit id="2">
      <segment>
        <source>Goodbye.</source>
        <target>再见。</target>
      </segment>
    </unit>
  </file>
</xliff>
XML;

		$units = $this->importer->parse( $xml );

		self::assertCount( 2, $units );
		self::assertSame( '1', $units[0]['unit_id'] );
		self::assertSame( 'Hello world.', $units[0]['source'] );
		self::assertSame( '你好世界。', $units[0]['target'] );
		self::assertSame( '2', $units[1]['unit_id'] );
		self::assertSame( '再见。', $units[1]['target'] );
	}

	public function testImportSkipsEmptyTargets(): void {
		$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.0" srcLang="en-us" trgLang="zh-cn">
  <file id="f1">
    <unit id="1">
      <segment>
        <source>Hello.</source>
        <target></target>
      </segment>
    </unit>
  </file>
</xliff>
XML;

		$units = $this->importer->parse( $xml );
		self::assertCount( 0, $units, 'Empty targets should be skipped.' );
	}

	public function testImportEmptyXml(): void {
		$units = $this->importer->parse( '' );
		self::assertCount( 0, $units );
	}

	public function testImportInvalidXml(): void {
		$units = $this->importer->parse( 'not xml at all' );
		self::assertCount( 0, $units );
	}

	public function testRoundTrip(): void {
		$segments = array(
			array(
				'id'              => 42,
				'segment_index'   => 0,
				'source_text'     => 'Hello world.',
				'translated_text' => 'Hola mundo.',
			),
		);

		$xml   = $this->exporter->export( 'en_US', 'es_ES', $segments );
		$units = $this->importer->parse( $xml );

		self::assertCount( 1, $units );
		self::assertSame( '42', $units[0]['unit_id'] );
		self::assertSame( 'Hello world.', $units[0]['source'] );
		self::assertSame( 'Hola mundo.', $units[0]['target'] );
	}
}
