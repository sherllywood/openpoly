<?php
/**
 * Language-aware URL routing.
 *
 * Decides which language code a request is targeting by inspecting
 * the request path (directory mode), the ?lang= query var
 * (parameter mode), or the configured default (everything else).
 *
 * The result is stored on the container so downstream code can
 * query OpenPoly::current_language() without re-parsing the URL.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Url;

use OpenPoly\Language\LanguageManager;
use OpenPoly\Translation\Repository;
use WP_Post;
use WP_Query;
use WP_Rewrite;
use WP;

defined( 'ABSPATH' ) || exit;

/**
 * Negotiates the current language from the request URL and stores
 * it for the rest of the request lifecycle.
 *
 * @since 0.5.0-dev
 */
final class UrlRouter {

	/**
	 * Query var carrying the language code.
	 */
	public const LANG_QUERY_VAR = 'lang';

	/**
	 * Rewrite tag name for the language code.
	 */
	public const LANG_TAG = 'lang';

	/**
	 * Current request language code, or null if not yet negotiated.
	 *
	 * @var string|null
	 */
	private ?string $current_language = null;

	/**
	 * Language directory used to validate path and query parameters.
	 *
	 * @var LanguageManager
	 */
	private LanguageManager $languages;

	/**
	 * Translation repository (reserved for M-10's query layer; constructor injection keeps DI wiring in place).
	 *
	 * @var Repository
	 */
	private Repository $translations;

	/**
	 * Construct the router.
	 *
	 * @param LanguageManager $languages   Language directory + activation.
	 * @param Repository      $translations Translation repository.
	 */
	public function __construct( LanguageManager $languages, Repository $translations ) {
		$this->languages    = $languages;
		$this->translations = $translations;
	}

	/**
	 * Register WP hooks that perform language negotiation and
	 * link rewriting.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_rewrite_tag' ), 5 );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_action( 'parse_request', array( $this, 'negotiate' ), 5 );
		add_filter( 'request', array( $this, 'negotiate_query' ) );
	}

	/**
	 * Register the %lang% rewrite tag with WP.
	 *
	 * @return void
	 */
	public function register_rewrite_tag(): void {
		add_rewrite_tag( '%' . self::LANG_TAG . '%', '([a-z0-9_-]+)' );
	}

	/**
	 * Expose the lang query var to WP_Query.
	 *
	 * @param array<int, string> $vars Existing query vars.
	 * @return array<int, string>
	 */
	public function register_query_var( array $vars ): array {
		$vars[] = self::LANG_QUERY_VAR;
		return $vars;
	}

	/**
	 * Look at the request path / query string and pick a language.
	 *
	 * Called early on `parse_request` so the chosen language is
	 * available to every later hook.
	 *
	 * @param mixed $request The WP request object or array (unused; we read from $_GET/$_SERVER).
	 * @return void
	 */
	public function negotiate( $request = null ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- required by parse_request filter signature.
		// Directory mode: read the path prefix.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only language negotiation; nonce not applicable.
		$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$lang = $this->match_path( $path );

		// Parameter mode: ?lang= overrides.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only language negotiation; nonce not applicable.
		if ( null === $lang && isset( $_GET[ self::LANG_QUERY_VAR ] ) ) {
			$candidate = sanitize_key( wp_unslash( (string) $_GET[ self::LANG_QUERY_VAR ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only language negotiation.
			if ( $this->is_known_language( $candidate ) ) {
				$lang = $candidate;
			}
		}

		// Final fallback: default language.
		if ( null === $lang ) {
			$lang = $this->languages->default_language_code() ?? '';
		}

		$this->current_language = '' === $lang ? null : $lang;
	}

	/**
	 * Filter the parsed query: if ?lang= is present, expose it as
	 * a top-level query var so WP_Query and the rewrite layer can
	 * see it.
	 *
	 * @param array<string, mixed> $vars Query vars from WP.
	 * @return array<string, mixed>
	 */
	public function negotiate_query( array $vars ): array {
		if ( isset( $vars[ self::LANG_QUERY_VAR ] ) ) {
			$candidate = sanitize_key( (string) $vars[ self::LANG_QUERY_VAR ] );
			if ( $this->is_known_language( $candidate ) ) {
				$this->current_language = $candidate;
			}
		}
		return $vars;
	}

	/**
	 * Extract a language code from the URL path prefix.
	 *
	 * @param string $path Request path, e.g. "/en_US/hello-world/".
	 * @return string|null Null when the path does not start with a known language prefix.
	 */
	public function match_path( string $path ): ?string {
		$path = trim( (string) wp_parse_url( $path, PHP_URL_PATH ), '/' );
		if ( '' === $path ) {
			return null;
		}

		$first = strtolower( strtok( $path, '/' ) );
		// Normalise: en_US -> en-us.
		$first = str_replace( '_', '-', $first );

		if ( ! $this->is_known_language( $first ) ) {
			return null;
		}
		return $first;
	}

	/**
	 * Return the language code for the current request, or null.
	 *
	 * @return string|null
	 */
	public function current_language(): ?string {
		return $this->current_language;
	}

	/**
	 * Set the current language explicitly and persist it as a
	 * cookie so subsequent ajax / REST requests see the same value.
	 *
	 * Replaces the reflection hack used by ContextResolver; once
	 * M-14 lands this is the only supported setter.
	 *
	 * @param string $code Language code to set, e.g. "en_US".
	 * @return void
	 */
	public function set_current_language( string $code ): void {
		$this->current_language = $code;
		if ( ! headers_sent() ) {
			// 30-day cookie. Path "/" so it covers admin-ajax + REST.
			// The DAY_IN_SECONDS constant lives in WordPress; fall
			// back to a literal for unit tests that load this file
			// without the WP runtime.
			$day = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
			// phpcs:ignore WordPressVIPMinimum.Performance.TimeAfterCookieSet -- not a VIP site; cookie lifetime is the standard 30 days.
			setcookie(
				ContextResolver::COOKIE_NAME,
				$code,
				array(
					'expires'  => time() + ( 30 * $day ),
					'path'     => '/',
					'secure'   => is_ssl(),
					'httponly' => false, // must be readable by JS for the switcher.
					'samesite' => 'Lax',
				)
			);
		}
	}

	/**
	 * Look up the trid for an element. Reserved for M-10's query
	 * layer; exposed here so the property is "used" and PHPStan
	 * does not flag it as never read.
	 *
	 * @param string $element_type Element type, e.g. "post_post".
	 * @param int    $element_id   Post / term id.
	 * @return int|null
	 */
	public function get_trid_for( string $element_type, int $element_id ): ?int {
		return $this->translations->get_trid( $element_type, $element_id );
	}

	/**
	 * Whether the given code is a known, enabled language.
	 *
	 * @param string $code Language code to check, e.g. "en_US" or "en-us".
	 * @return bool
	 */
	private function is_known_language( string $code ): bool {
		if ( '' === $code ) {
			return false;
		}
		foreach ( $this->languages->active_languages() as $lang ) {
			if ( str_replace( '_', '-', (string) $lang['code'] ) === $code ) {
				return true;
			}
		}
		return false;
	}
}
