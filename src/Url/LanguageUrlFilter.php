<?php
/**
 * Rewrites frontend links to include the current language prefix.
 *
 * Mounted as a filter on post_link / page_link / term_link /
 * home_url, so that anywhere WP builds a permalink it ends up with
 * the directory-mode prefix when needed.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Url;

use OpenPoly\Language\LanguageManager;
use OpenPoly\Url\UrlRouter;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Pure helper that prepends a language code to a URL when needed.
 *
 * @since 0.5.0-dev
 */
final class LanguageUrlFilter {

	/**
	 * Underlying language directory.
	 *
	 * @var LanguageManager
	 */
	private LanguageManager $languages;

	/**
	 * Underlying URL router, used to read the current request language.
	 *
	 * @var UrlRouter
	 */
	private UrlRouter $router;

	/**
	 * Construct the filter.
	 *
	 * @param LanguageManager $languages Language directory.
	 * @param UrlRouter       $router    URL router for the current request.
	 */
	public function __construct( LanguageManager $languages, UrlRouter $router ) {
		$this->languages = $languages;
		$this->router    = $router;
	}

	/**
	 * Register the WP filters.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'post_link', array( $this, 'filter_post_link' ), 10, 2 );
		add_filter( 'page_link', array( $this, 'filter_post_link' ), 10, 2 );
		add_filter( 'post_type_link', array( $this, 'filter_post_link' ), 10, 2 );
		add_filter( 'term_link', array( $this, 'filter_term_link' ), 10, 2 );
		add_filter( 'home_url', array( $this, 'filter_home_url' ), 10, 2 );
	}

	/**
	 * Prepend a language code to a post permalink.
	 *
	 * @param string      $url   Original permalink.
	 * @param int|WP_Post $post  Post id or object (unused; required by WP filter signature).
	 * @return string
	 */
	public function filter_post_link( $url, $post = null ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- required by WP filter signature.
		return $this->add_language_to_url( (string) $url );
	}

	/**
	 * Prepend a language code to a term permalink.
	 *
	 * @param string $url    Original term link.
	 * @param mixed  $term   Term object (unused, kept for filter signature).
	 * @return string
	 */
	public function filter_term_link( $url, $term = null ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- required by WP filter signature.
		return $this->add_language_to_url( (string) $url );
	}

	/**
	 * Prepend a language code to the home URL.
	 *
	 * @param string      $url   Original home URL.
	 * @param string|null $path Optional path.
	 * @return string
	 */
	public function filter_home_url( $url, $path = null ): string {
		unset( $path );
		return $this->add_language_to_url( (string) $url );
	}

	/**
	 * Pure: prepend a language prefix to a URL.
	 *
	 * @param string $url Original URL.
	 * @return string URL with /<lang>/ prepended (or unchanged if already prefixed or no language).
	 */
	public function add_language_to_url( string $url ): string {
		$lang = $this->router->current_language();
		if ( null === $lang ) {
			return $url;
		}

		$prefix  = str_replace( '_', '-', strtolower( $lang ) );
		$default = $this->languages->default_language_code();
		if ( null !== $default && $default === $lang ) {
			// Default language does not need a prefix; convention.
			return $url;
		}

		// Parse the URL into parts so we only touch the path.
		$parts = wp_parse_url( $url );
		if ( false === $parts || ! isset( $parts['host'] ) ) {
			// Relative or malformed URL: prepend directly.
			$path = isset( $parts['path'] ) ? $parts['path'] : $url;
			return '/' . $prefix . $path;
		}

		$path     = isset( $parts['path'] ) ? $parts['path'] : '';
		$query    = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
		$fragment = isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '';
		$scheme   = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '//';
		$host     = $parts['host'];
		$port     = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		$user     = isset( $parts['user'] ) ? $parts['user'] : '';
		$pass     = isset( $parts['user'] ) && isset( $parts['pass'] ) ? ':' . $parts['pass'] : '';
		if ( '' !== $user ) {
			$user .= ( '' !== $pass ? '@' : '' );
		}

		return $scheme . $user . $host . $port . '/' . $prefix . ltrim( $path, '/' ) . $query . $fragment;
	}
}
