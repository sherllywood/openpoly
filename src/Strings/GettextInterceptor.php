<?php
/**
 * Intercepts gettext calls to serve translations from OpenPoly's
 * string table instead of WordPress's built-in MO files.
 *
 * On each request it pre-fetches every translation for the current
 * (domain, language) pair into memory so that the gettext filter
 * runs zero SQL queries (NFR-001).
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Strings;

use OpenPoly\Language\LanguageManager;
use OpenPoly\Url\UrlRouter;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into gettext and substitutes translations from op_strings.
 *
 * @since 0.5.0-dev
 */
final class GettextInterceptor {

	/**
	 * In-memory cache of loaded translations.
	 *
	 * @var array<string, array<string, string>> domain+code => md5_map
	 */
	private array $cache = array();

	/**
	 * String repository for loading translations.
	 *
	 * @var StringRepository
	 */
	private StringRepository $repository;

	/**
	 * Language manager for current locale.
	 *
	 * Used to validate that the current language is active.
	 *
	 * @var LanguageManager
	 */
	private LanguageManager $languages;

	/**
	 * URL router for current language detection.
	 *
	 * @var UrlRouter
	 */
	private UrlRouter $router;

	/**
	 * Constructor.
	 *
	 * @param StringRepository $repository String repository.
	 * @param LanguageManager  $languages  Language manager.
	 * @param UrlRouter        $router     URL router.
	 */
	public function __construct( StringRepository $repository, LanguageManager $languages, UrlRouter $router ) {
		$this->repository = $repository;
		$this->languages  = $languages;
		$this->router     = $router;
	}

	/**
	 * Register the gettext hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'gettext', array( $this, 'translate' ), 20, 3 );
		add_filter( 'gettext_with_context', array( $this, 'translate_with_context' ), 20, 4 );
		add_action( 'init', array( $this, 'prefetch' ), 0 );
	}

	/**
	 * Pre-fetch all translations for the current request's language.
	 *
	 * Runs early on `init` to populate the in-memory cache before
	 * any gettext filter fires. This is the mechanism that keeps
	 * the hot path SQL-free (NFR-001).
	 *
	 * @return void
	 */
	public function prefetch(): void {
		$lang = $this->router->current_language();
		if ( null === $lang || ! $this->is_active_language( $lang ) ) {
			return;
		}

		// Pre-fetch 'default' domain (the theme / plugin domain
		// is resolved dynamically in translate()).
		$key                 = 'default|' . $lang;
		$this->cache[ $key ] = $this->repository->load_translations( 'default', $lang );
	}

	/**
	 * Translate a gettext string.
	 *
	 * @param string $translation Existing translation from MO.
	 * @param string $text        Original text.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public function translate( $translation, $text, $domain ): string {
		$lang = $this->router->current_language();
		if ( null === $lang || ! $this->is_active_language( $lang ) ) {
			return $translation;
		}

		$domain_key = (string) $domain;
		$cache_key  = $domain_key . '|' . $lang;

		if ( ! isset( $this->cache[ $cache_key ] ) ) {
			$this->cache[ $cache_key ] = $this->repository->load_translations( $domain_key, $lang );
		}

		$md5 = md5( $domain_key . '||' . (string) $text );

		if ( isset( $this->cache[ $cache_key ][ $md5 ] ) ) {
			return $this->cache[ $cache_key ][ $md5 ];
		}

		return $translation;
	}

	/**
	 * Translate a gettext string with context.
	 *
	 * @param string $translation Existing translation from MO.
	 * @param string $text        Original text.
	 * @param string $context     Gettext context.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public function translate_with_context( $translation, $text, $context, $domain ): string {
		$lang = $this->router->current_language();
		if ( null === $lang || ! $this->is_active_language( $lang ) ) {
			return $translation;
		}

		$domain_key = (string) $domain;
		$cache_key  = $domain_key . '|' . $lang;

		if ( ! isset( $this->cache[ $cache_key ] ) ) {
			$this->cache[ $cache_key ] = $this->repository->load_translations( $domain_key, $lang );
		}

		$md5 = md5( $domain_key . '|' . (string) $context . '|' . (string) $text );

		if ( isset( $this->cache[ $cache_key ][ $md5 ] ) ) {
			return $this->cache[ $cache_key ][ $md5 ];
		}

		return $translation;
	}

	/**
	 * Check whether a language code is among the active languages.
	 *
	 * @param string $code Language code.
	 * @return bool
	 */
	private function is_active_language( string $code ): bool {
		foreach ( $this->languages->active_languages() as $row ) {
			if ( (string) ( $row['code'] ?? '' ) === $code ) {
				return true;
			}
		}
		return false;
	}
}
