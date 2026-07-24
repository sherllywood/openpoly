<?php
/**
 * Taxonomy (term) query interception.
 *
 * Adds a language-aware JOIN + WHERE to term queries on the
 * front end, mirroring the post filter (M-10). Terms whose
 * translation group has no entry in the current request language
 * are excluded unless the group has no source either.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Query;

use OpenPoly\Language\LanguageManager;
use OpenPoly\Url\UrlRouter;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the terms_clauses filter that constrains term lookups
 * to the current request language.
 *
 * @since 0.5.0-dev
 */
final class TermFilter {

	/**
	 * Meta key that opts a term query out of the filter.
	 *
	 * Usage: WP_Term_Query::set( 'openpoly_lang_skip', '1' )
	 */
	public const SKIP_QUERY_VAR = 'openpoly_lang_skip';

	/**
	 * URL router used to read the current request language.
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
	 * Construct the term filter.
	 *
	 * @param UrlRouter       $router    URL router (current request language).
	 * @param LanguageManager $languages Language directory.
	 */
	public function __construct( UrlRouter $router, LanguageManager $languages ) {
		$this->router    = $router;
		$this->languages = $languages;
	}

	/**
	 * Register the terms_clauses filter.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'terms_clauses', array( $this, 'filter_terms_clauses' ), 10, 3 );
	}

	/**
	 * Restrict a term query to terms visible in the current
	 * request language, unless the caller opted out.
	 *
	 * @param array<string, string> $clauses    Term query clauses (where, join, ...).
	 * @param array<int, string>    $taxonomies Taxonomies being queried.
	 * @param array<string, mixed>  $args       Original query args.
	 * @return array<string, string>
	 */
	public function filter_terms_clauses( $clauses, $taxonomies, $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $taxonomies and $args required by WP filter signature.
		if ( ! is_array( $clauses ) ) {
			return $clauses;
		}

		// Honour explicit opt-out.
		if ( ! empty( $args[ self::SKIP_QUERY_VAR ] ) ) {
			return $clauses;
		}

		// Skip admin, REST, and term-link singletons (covered by
		// get_term in singular pages; we don't want to clobber
		// those queries).
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $clauses;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $clauses;
		}

		$lang = $this->resolve_language();
		if ( null === $lang ) {
			return $clauses;
		}

		// Build the element_type list. e.g. for ['category','post_tag']
		// the join needs 'tax_category' OR 'tax_post_tag'.
		$type_clauses = $this->build_type_clauses( $taxonomies );
		if ( empty( $type_clauses ) ) {
			return $clauses;
		}

		$on_clause = implode( ' OR ', $type_clauses );
		$safe      = esc_sql( $lang );
		$where     = " op_t.language_code = '{$safe}' OR op_t.translation_id IS NULL ";

		global $wpdb;
		$join      = $clauses['join'] ?? '';
		$join     .= " LEFT JOIN {$wpdb->prefix}op_translations AS op_t ON (op_t.element_id = {$wpdb->term_taxonomy}.term_id AND ({$on_clause})) ";
		$where_sql = $clauses['where'] ?? '1=1';
		$where_sql = $where_sql . ' AND ( ' . $where . ' ) ';

		$clauses['join']  = $join;
		$clauses['where'] = $where_sql;

		return $clauses;
	}

	/**
	 * Build per-taxonomy element_type expressions for the join.
	 *
	 * @param array<int, string> $taxonomies Taxonomy slugs to filter on.
	 * @return array<int, string>
	 */
	public function build_type_clauses( array $taxonomies ): array {
		$out = array();
		foreach ( $taxonomies as $tax ) {
			if ( ! is_string( $tax ) || '' === $tax ) {
				continue;
			}
			$safe  = esc_sql( $tax );
			$out[] = "op_t.element_type = 'tax_{$safe}'";
		}
		return $out;
	}

	/**
	 * Resolve the current request language and validate it.
	 *
	 * @return string|null
	 */
	public function resolve_language(): ?string {
		$current = $this->router->current_language();
		if ( null === $current ) {
			return null;
		}
		foreach ( $this->languages->active_languages() as $lang ) {
			if ( (string) $lang['code'] === $current ) {
				return $current;
			}
		}
		return null;
	}
}
