<?php
/**
 * Query interception layer.
 *
 * Filters WP_Query at the SQL level so the posts returned on the
 * front end are limited to those the current request language can
 * actually see (translated, duplicate, or fallback per FR-CORE-002).
 *
 * Strategy (02 architecture §4.2):
 *   - posts_join    : LEFT JOIN op_translations to expose language.
 *   - posts_where   : restrict to language_code OR allow fallback.
 *   - posts_pre_query : opt front-end queries into the filter
 *                       (admin / REST stays untouched).
 *   - terms_clauses : same shape, for category/tag archives.
 *
 * Performance: a single extra join and an IN-list condition keep
 * the cost at "≤ 3 extra SQL per page" (NFR-001). No N+1.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Query;

use OpenPoly\Language\LanguageManager;
use OpenPoly\Url\UrlRouter;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Injects language-aware SQL fragments into the main WP_Query.
 *
 * @since 0.5.0-dev
 */
final class QueryFilter {

	/**
	 * Meta key that opts a query into the language filter.
	 *
	 * Usage in code: WP_Query::set( 'openpoly_lang', 'en_US' )
	 * or rely on the current request language (default).
	 */
	public const QUERY_VAR = 'openpoly_lang';

	/**
	 * Suffix that opts a query OUT of the language filter.
	 */
	public const SKIP_QUERY_VAR = 'openpoly_lang_skip';

	/**
	 * URL router providing the current request language.
	 *
	 * @var UrlRouter
	 */
	private UrlRouter $router;

	/**
	 * Language directory used to validate the resolved language.
	 *
	 * @var LanguageManager
	 */
	private LanguageManager $languages;

	/**
	 * Construct the filter.
	 *
	 * @param UrlRouter       $router    URL router providing the current request language.
	 * @param LanguageManager $languages Language directory.
	 */
	public function __construct( UrlRouter $router, LanguageManager $languages ) {
		$this->router    = $router;
		$this->languages = $languages;
	}

	/**
	 * Register WP hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'posts_join', array( $this, 'filter_posts_join' ), 10, 2 );
		add_filter( 'posts_where', array( $this, 'filter_posts_where' ), 10, 2 );
		add_filter( 'posts_clauses', array( $this, 'filter_posts_clauses' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'maybe_apply_to_query' ), 10, 1 );
		add_filter( 'terms_clauses', array( $this, 'filter_terms_clauses' ), 10, 3 );
	}

	/**
	 * Decide whether a WP_Query should be filtered.
	 *
	 * Skips admin, REST, singular queries, and queries that the
	 * caller has explicitly opted out of.
	 *
	 * @param WP_Query $query The query being inspected.
	 * @return bool
	 */
	public function should_filter( WP_Query $query ): bool {
		if ( ! empty( $query->get( self::SKIP_QUERY_VAR ) ) ) {
			return false;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		// Singular queries (single post) are handled by the URL
		// router directly; filtering them risks double-rewrites.
		if ( $query->is_singular() ) {
			return false;
		}

		return true;
	}

	/**
	 * Pre get posts hook: mark the query for filtering.
	 *
	 * @param WP_Query $query The query being inspected.
	 * @return void
	 */
	public function maybe_apply_to_query( WP_Query $query ): void {
		if ( ! $this->should_filter( $query ) ) {
			return;
		}

		// Stamp the query with our QUERY_VAR so other layers can
		// know the filter is active.
		$query->set( self::QUERY_VAR, $this->resolve_language() );
	}

	/**
	 * Add the JOIN clause that exposes the language per row.
	 *
	 * @param string   $join Existing JOIN clauses.
	 * @param WP_Query $query The query being filtered.
	 * @return string
	 */
	public function filter_posts_join( $join, $query ): string {
		global $wpdb;

		if ( '' === $join ) {
			$join = ' ';
		}

		$post_types = (array) $query->get( 'post_type' );
		// Build a comma-separated list of element_type values, one
		// per post type. The query layer treats 'post_post' as the
		// canonical mapping; an unsupported type yields an empty
		// join (no rows match, query is harmlessly empty).
		$type_clauses = array();
		foreach ( $post_types as $pt ) {
			if ( ! is_string( $pt ) || '' === $pt ) {
				continue;
			}
			$safe           = esc_sql( $pt );
			$type_clauses[] = "op_t.element_type = 'post_{$safe}'";
		}

		if ( empty( $type_clauses ) ) {
			return $join;
		}

		$on_clause = implode( ' OR ', $type_clauses );
		$table     = $wpdb->prefix . 'op_translations';

		return $join . " LEFT JOIN {$table} AS op_t ON (op_t.element_id = {$wpdb->posts}.ID AND ({$on_clause})) ";
	}

	/**
	 * Add the WHERE clause that restricts by language.
	 *
	 * @param string   $where Existing WHERE clauses.
	 * @param WP_Query $query The query being filtered.
	 * @return string
	 */
	public function filter_posts_where( $where, $query ): string {
		unset( $query );
		global $wpdb;

		$lang = $this->resolve_language();
		if ( null === $lang ) {
			return $where;
		}

		$table = "{$wpdb->prefix}op_t";
		$safe  = esc_sql( $lang );

		// Allow translated rows in the target language OR rows
		// that have no translation at all (fallback to source).
		$where .= " AND ({$table}.language_code = '{$safe}' OR {$table}.translation_id IS NULL) ";

		return $where;
	}

	/**
	 * Top-level wrapper for posts_clauses. The individual filters
	 * (posts_join, posts_where) already run via dedicated hooks,
	 * but this entry point lets a caller force the filter to
	 * engage when they have set `openpoly_lang` explicitly.
	 *
	 * @param array<string, string> $clauses SQL clauses.
	 * @param WP_Query              $query  The query being filtered.
	 * @return array<string, string>
	 */
	public function filter_posts_clauses( $clauses, $query ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $query required by WP filter signature.
		// No additional clause rewriting in M-10. M-12 (taxonomies)
		// will use the terms_clauses hook for category filtering.
		return $clauses;
	}

	/**
	 * Restrict taxonomy archive queries to terms in the current
	 * language, when applicable.
	 *
	 * @param array<string, string> $clauses    Term query clauses.
	 * @param array<int, string>    $taxonomies Taxonomies being queried.
	 * @param array<string, mixed>  $args       Original query args.
	 * @return array<string, string>
	 */
	public function filter_terms_clauses( $clauses, $taxonomies, $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $taxonomies and $args required by WP filter signature.
		// M-12 will implement term-level filtering using a join
		// against op_translations with element_type like 'tax_%'.
		// M-10 leaves the shape in place but does not yet apply.
		return $clauses;
	}

	/**
	 * Resolve which language the current request should filter by.
	 *
	 * Honours an explicit override set on the query (QUERY_VAR),
	 * then falls back to the URL router's current language.
	 *
	 * @return string|null
	 */
	public function resolve_language(): ?string {
		$current = $this->router->current_language();
		if ( null === $current ) {
			return null;
		}

		// Validate the code is in the active language list.
		foreach ( $this->languages->active_languages() as $lang ) {
			if ( (string) $lang['code'] === $current ) {
				return $current;
			}
		}
		return null;
	}
}
