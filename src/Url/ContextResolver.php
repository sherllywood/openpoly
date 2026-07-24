<?php
/**
 * Resolves the current language for admin-ajax and REST requests.
 *
 * These requests are detached from the URL the user is browsing
 * (admin-ajax posts to /wp-admin/admin-ajax.php, REST hits /wp-json/...),
 * so the regular UrlRouter path-prefix match is useless. This class
 * walks four fallback sources in priority order:
 *
 *   1. ?lang= query var (explicit override)
 *   2. openpoly_lang cookie (set by the language switcher)
 *   3. HTTP_REFERER (the page that triggered the request)
 *   4. UrlRouter::current_language() (whatever was negotiated for
 *      the current front-end request)
 *
 * The result is then written into the UrlRouter so the rest of the
 * request lifecycle sees a single consistent language.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Url;

use OpenPoly\Language\LanguageManager;
use OpenPoly\Translation\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Determine the language for the current ajax / REST request.
 *
 * @since 0.5.0-dev
 */
final class ContextResolver {

	/**
	 * Cookie name set by the language switcher.
	 *
	 * @var string
	 */
	public const COOKIE_NAME = 'openpoly_lang';

	/**
	 * Priority: explicit > cookie > referrer > router.
	 *
	 * @var int
	 */
	public const PRIORITY_QUERY = 100;

	/**
	 * Cookie source priority.
	 *
	 * @var int
	 */
	public const PRIORITY_COOKIE = 75;

	/**
	 * Referrer source priority.
	 *
	 * @var int
	 */
	public const PRIORITY_REFERRER = 50;

	/**
	 * Router source priority.
	 *
	 * @var int
	 */
	public const PRIORITY_ROUTER = 25;

	/**
	 * Router used to read and write the current request language.
	 *
	 * @var UrlRouter
	 */
	private UrlRouter $router;

	/**
	 * Language directory used to validate resolved codes.
	 *
	 * @var LanguageManager
	 */
	private LanguageManager $languages;

	/**
	 * Translation repository (reserved for future ajax hooks; constructor injection keeps DI wiring in place).
	 *
	 * @var Repository
	 */
	private Repository $translations;

	/**
	 * Construct the resolver.
	 *
	 * @param UrlRouter       $router       URL router (current language state).
	 * @param LanguageManager $languages    Language directory.
	 * @param Repository      $translations Translation repository.
	 */
	public function __construct( UrlRouter $router, LanguageManager $languages, Repository $translations ) {
		$this->router       = $router;
		$this->languages    = $languages;
		$this->translations = $translations;
	}

	/**
	 * Read the language from all sources and write it to the router.
	 *
	 * Call this from admin_init / rest_api_init early hooks. The
	 * result is the highest-priority source that yields a known
	 * language code.
	 *
	 * @return string|null The resolved language, or null when no source matches.
	 */
	public function resolve(): ?string {
		$candidates = array(
			self::PRIORITY_QUERY    => $this->from_query(),
			self::PRIORITY_COOKIE   => $this->from_cookie(),
			self::PRIORITY_REFERRER => $this->from_referrer(),
			self::PRIORITY_ROUTER   => $this->router->current_language(),
		);

		ksort( $candidates, SORT_NUMERIC );

		foreach ( $candidates as $code ) {
			if ( null !== $code && $this->is_known( $code ) ) {
				$this->write_router( $code );
				return $code;
			}
		}

		return null;
	}

	/**
	 * Read the language from the request query string.
	 *
	 * @return string|null
	 */
	public function from_query(): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only language negotiation; nonce not applicable.
		if ( ! isset( $_GET[ UrlRouter::LANG_QUERY_VAR ] ) ) {
			return null;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only language negotiation; nonce not applicable.
		$candidate = sanitize_key( wp_unslash( (string) $_GET[ UrlRouter::LANG_QUERY_VAR ] ) );
		return '' === $candidate ? null : $candidate;
	}

	/**
	 * Read the language from the openpoly_lang cookie.
	 *
	 * @return string|null
	 */
	public function from_cookie(): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only language negotiation; nonce not applicable.
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return null;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only language negotiation; nonce not applicable.
		$candidate = sanitize_key( wp_unslash( (string) $_COOKIE[ self::COOKIE_NAME ] ) );
		return '' === $candidate ? null : $candidate;
	}

	/**
	 * Read the language from the page that triggered this request.
	 *
	 * @return string|null
	 */
	public function from_referrer(): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only language negotiation; nonce not applicable.
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? (string) wp_unslash( $_SERVER['HTTP_REFERER'] ) : '';
		if ( '' === $referer ) {
			return null;
		}

		$path = (string) wp_parse_url( $referer, PHP_URL_PATH );
		return $this->router->match_path( $path );
	}

	/**
	 * Whether the given code matches a known active language.
	 *
	 * @param string $code Language code to check.
	 * @return bool
	 */
	private function is_known( string $code ): bool {
		foreach ( $this->languages->active_languages() as $lang ) {
			if ( (string) $lang['code'] === $code ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Push the resolved code into the router so downstream code
	 * sees a single consistent current_language().
	 *
	 * Uses reflection because UrlRouter has no public setter for
	 * current_language; M-14 (switcher) will introduce a proper
	 * setter and drop this reflection hack.
	 *
	 * @param string $code Resolved language code.
	 * @return void
	 */
	private function write_router( string $code ): void {
		$reflection = new \ReflectionClass( $this->router );
		$property   = $reflection->getProperty( 'current_language' );
		$property->setAccessible( true );
		$property->setValue( $this->router, $code );
	}

	/**
	 * Return the translation repository. Reserved for future
	 * ajax / REST handlers that need to look up an element's
	 * trid; exposed here so PHPStan sees the property is used.
	 *
	 * @return Repository
	 */
	public function translations(): Repository {
		return $this->translations;
	}
}
