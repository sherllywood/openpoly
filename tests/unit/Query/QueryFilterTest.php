<?php
/**
 * Test: QueryFilter.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Query;

use OpenPoly\Language\LanguageManager;
use OpenPoly\Query\QueryFilter;
use OpenPoly\Url\UrlRouter;
use PHPUnit\Framework\TestCase;
use WP_Query;

/**
 * @covers \OpenPoly\Query\QueryFilter
 */
final class QueryFilterTest extends TestCase {

	public function testResolveLanguageFallsBackToRouterCurrent(): void {
		$filter = $this->make_filter( 'en_US', array( $this->row( 'en_US' ) ) );

		self::assertSame( 'en_US', $filter->resolve_language() );
	}

	public function testResolveLanguageReturnsNullForUnknownCode(): void {
		$filter = $this->make_filter( 'fr_FR', array( $this->row( 'en_US' ) ) );

		self::assertNull( $filter->resolve_language() );
	}

	public function testResolveLanguageReturnsNullWhenNoCurrent(): void {
		$filter = $this->make_filter( null, array( $this->row( 'en_US' ) ) );

		self::assertNull( $filter->resolve_language() );
	}

	public function testFilterPostsJoinAddsLeftJoin(): void {
		$filter = $this->make_filter( 'en_US', array( $this->row( 'en_US' ) ) );

		$query = new WP_Query();
		$query->set( 'post_type', 'post' );

		$join = $filter->filter_posts_join( '', $query );

		self::assertStringContainsString( 'LEFT JOIN', $join );
		self::assertStringContainsString( 'op_t', $join );
		self::assertStringContainsString( "op_t.element_type = 'post_post'", $join );
	}

	public function testFilterPostsWhereAddsLanguageRestriction(): void {
		$filter = $this->make_filter( 'en_US', array( $this->row( 'en_US' ) ) );

		$where = $filter->filter_posts_where( '', $this->createMock( WP_Query::class ) );

		self::assertStringContainsString( "language_code = 'en_US'", $where );
		self::assertStringContainsString( 'translation_id IS NULL', $where );
	}

	public function testFilterPostsWhereIsNoOpWhenNoLanguage(): void {
		$filter = $this->make_filter( null, array( $this->row( 'en_US' ) ) );

		$where = $filter->filter_posts_where( 'existing_where', $this->createMock( WP_Query::class ) );

		self::assertSame( 'existing_where', $where, 'No language means no language WHERE clause.' );
	}

	public function testShouldFilterSkipsSingularQueries(): void {
		$filter = $this->make_filter( 'en_US', array() );

		$query = new WP_Query();
		$query->is_singular = true;

		self::assertFalse( $filter->should_filter( $query ) );
	}

	public function testShouldFilterHonoursSkipOverride(): void {
		$filter = $this->make_filter( 'en_US', array() );

		$query = new WP_Query();
		$query->set( QueryFilter::SKIP_QUERY_VAR, '1' );

		self::assertFalse( $filter->should_filter( $query ) );
	}

	public function testShouldFilterAllowsNormalQuery(): void {
		$filter = $this->make_filter( 'en_US', array() );

		$query = new WP_Query();
		self::assertTrue( $filter->should_filter( $query ) );
	}

	public function testQueryVarConstantIsStable(): void {
		self::assertSame( 'openpoly_lang', QueryFilter::QUERY_VAR );
	}

	/**
	 * Build a QueryFilter with the given request language and active
	 * language list.
	 *
	 * @param string|null $current         Router's current language, or null.
	 * @param array<int, array<string, mixed>> $active_languages
	 * @return QueryFilter
	 */
	private function make_filter( ?string $current, array $active_languages ): QueryFilter {
		$router = $this->createMock( UrlRouter::class );
		$router->method( 'current_language' )->willReturn( $current );

		$languages = $this->createMock( LanguageManager::class );
		$languages->method( 'active_languages' )->willReturn( $active_languages );

		return new QueryFilter( $router, $languages );
	}

	/**
	 * Fake language row.
	 *
	 * @param string $code
	 * @return array<string, mixed>
	 */
	private function row( string $code ): array {
		return array( 'id' => 1, 'code' => $code, 'is_active' => 1 );
	}
}
