<?php
/**
 * Test: TermFilter.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Query;

use OpenPoly\Language\LanguageManager;
use OpenPoly\Query\TermFilter;
use OpenPoly\Url\UrlRouter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Query\TermFilter
 */
final class TermFilterTest extends TestCase {

	public function testBuildTypeClausesForMultipleTaxonomies(): void {
		$filter = $this->make_filter( 'en_US', array() );

		$clauses = $filter->build_type_clauses( array( 'category', 'post_tag' ) );

		self::assertCount( 2, $clauses );
		self::assertSame( "op_t.element_type = 'tax_category'", $clauses[0] );
		self::assertSame( "op_t.element_type = 'tax_post_tag'", $clauses[1] );
	}

	public function testBuildTypeClausesSkipsEmptyTaxonomies(): void {
		$filter = $this->make_filter( 'en_US', array() );

		$clauses = $filter->build_type_clauses( array( 'category', '', null ) );

		self::assertCount( 1, $clauses );
		self::assertSame( "op_t.element_type = 'tax_category'", $clauses[0] );
	}

	public function testFilterClausesLeavesUnknownLanguageUnchanged(): void {
		$languages = $this->createMock( LanguageManager::class );
		$languages->method( 'active_languages' )->willReturn( array( $this->lang( 'en_US' ) ) );

		$router = $this->createMock( UrlRouter::class );
		$router->method( 'current_language' )->willReturn( 'xx_XX' );

		$filter = new TermFilter( $router, $languages );

		$clauses = array(
			'join'  => '',
			'where' => '1=1',
		);
		$out = $filter->filter_terms_clauses( $clauses, array( 'category' ), array() );

		self::assertSame( $clauses, $out, 'Unknown language means no JOIN/WHERE appended.' );
	}

	public function testFilterClausesAppendsJoinAndWhere(): void {
		$filter = $this->make_filter( 'fr_FR', array( $this->lang( 'fr_FR' ) ) );

		$input = array(
			'join'  => 'EXISTING_JOIN',
			'where' => 'EXISTING_WHERE',
		);
		$out = $filter->filter_terms_clauses( $input, array( 'category' ), array() );

		self::assertStringContainsString( 'EXISTING_JOIN', $out['join'] );
		self::assertStringContainsString( 'op_t.element_type = \'tax_category\'', $out['join'] );
		self::assertStringContainsString( 'LEFT JOIN', $out['join'] );

		self::assertStringContainsString( 'EXISTING_WHERE', $out['where'] );
		self::assertStringContainsString( "language_code = 'fr_FR'", $out['where'] );
		self::assertStringContainsString( 'translation_id IS NULL', $out['where'] );
	}

	public function testFilterClausesHandlesEmptyInputArray(): void {
		$filter = $this->make_filter( 'en_US', array( $this->lang( 'en_US' ) ) );

		$out = $filter->filter_terms_clauses( array(), array( 'category' ), array() );

		self::assertArrayHasKey( 'join', $out );
		self::assertArrayHasKey( 'where', $out );
	}

	public function testFilterClausesHonoursSkipOverride(): void {
		$filter = $this->make_filter( 'en_US', array( $this->lang( 'en_US' ) ) );

		$input = array(
			'join'  => 'EXISTING_JOIN',
			'where' => 'EXISTING_WHERE',
		);
		$out = $filter->filter_terms_clauses(
			$input,
			array( 'category' ),
			array( TermFilter::SKIP_QUERY_VAR => '1' )
		);

		self::assertSame( $input, $out, 'Opt-out flag must bypass the filter entirely.' );
	}

	public function testFilterClausesHandlesEmptyTaxonomies(): void {
		$filter = $this->make_filter( 'en_US', array( $this->lang( 'en_US' ) ) );

		$input = array(
			'join'  => 'EXISTING_JOIN',
			'where' => 'EXISTING_WHERE',
		);
		$out = $filter->filter_terms_clauses( $input, array(), array() );

		self::assertSame( $input, $out, 'Empty taxonomies list means nothing to JOIN on.' );
	}

	/**
	 * Build a TermFilter with a fixed current language and active list.
	 *
	 * @param string                $current  Router's current language code.
	 * @param array<int, array<string, mixed>> $active_languages
	 * @return TermFilter
	 */
	private function make_filter( string $current, array $active_languages ): TermFilter {
		$router = $this->createMock( UrlRouter::class );
		$router->method( 'current_language' )->willReturn( $current );

		$languages = $this->createMock( LanguageManager::class );
		$languages->method( 'active_languages' )->willReturn( $active_languages );

		$filter = new TermFilter( $router, $languages );
		return $filter;
	}

	/**
	 * Build a fake language row.
	 *
	 * @param string $code
	 * @return array<string, mixed>
	 */
	private function lang( string $code ): array {
		return array( 'id' => 1, 'code' => $code, 'is_active' => 1 );
	}
}
