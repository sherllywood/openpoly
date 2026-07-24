<?php
/**
 * Generates the WP rewrite rules that map language directory URLs.
 *
 * Pattern: ^zh-hans/(.*)$ -> index.php?lang=zh-hans&$matches[1]
 *
 * The rules are built deterministically from the list of active
 * languages, so adding a new language re-flushes to make it work.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Url;

defined( 'ABSPATH' ) || exit;

/**
 * Pure helper that produces rewrite rule strings.
 *
 * @since 0.5.0-dev
 */
final class RewriteGenerator {

	/**
	 * Generate (pattern, replacement) pairs for one language.
	 *
	 * @param string $code Language code, e.g. "en_US".
	 * @return array<int, array{pattern:string, replacement:string}>
	 */
	public static function rules_for_language( string $code ): array {
		$prefix = str_replace( '_', '-', strtolower( $code ) );
		return array(
			array(
				'pattern'     => '^' . $prefix . '/?$',
				'replacement' => 'index.php?lang=' . $code,
			),
			array(
				'pattern'     => '^' . $prefix . '/(.+?)/?$',
				'replacement' => 'index.php?lang=' . $code . '&pagename=$matches[1]',
			),
		);
	}

	/**
	 * Build the array shape that WP expects for $wp_rewrite->rules.
	 *
	 * @param array<int, string> $language_codes List of active language codes.
	 * @return array<string, string>
	 */
	public static function build_rules( array $language_codes ): array {
		$rules = array();
		foreach ( $language_codes as $code ) {
			foreach ( self::rules_for_language( $code ) as $rule ) {
				$rules[ $rule['pattern'] ] = $rule['replacement'];
			}
		}
		return $rules;
	}

	/**
	 * Augment WP's existing rules with language-prefixed rules.
	 *
	 * @param array<string, string>|null $existing Existing rewrite rules, or null when WP has not yet initialized them.
	 * @param array<int, string>         $language_codes Active language codes.
	 * @return array<string, string>
	 */
	public static function merge( ?array $existing, array $language_codes ): array {
		$lang_rules = self::build_rules( $language_codes );
		$existing   = $existing ?? array();
		// Language rules go FIRST so they take precedence over the
		// catch-all WordPress rules that follow.
		return array_merge( $lang_rules, $existing );
	}
}
