<?php
/**
 * Front-end language switcher.
 *
 * Renders the HTML for the user-facing switcher in three forms:
 *   - list (default)    : <ul class="openpoly-switcher">
 *   - dropdown           : <select> with JS redirect on change
 *   - inline links       : <span class="openpoly-switcher-inline">
 *
 * Each link points at the current request URL with the target
 * language code swapped in, computed by LanguageUrlFilter.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Switcher;

use OpenPoly\Language\LanguageManager;
use OpenPoly\Url\LanguageUrlFilter;
use OpenPoly\Url\UrlRouter;

defined( 'ABSPATH' ) || exit;

/**
 * Pure helper that builds the switcher HTML for the current request.
 *
 * @since 0.5.0-dev
 */
final class Switcher {

	/**
	 * Layout types supported by render().
	 */
	public const LAYOUT_LIST     = 'list';
	public const LAYOUT_DROPDOWN = 'dropdown';
	public const LAYOUT_INLINE   = 'inline';

	/**
	 * URL router used to read the current request language.
	 *
	 * @var UrlRouter
	 */
	private UrlRouter $router;

	/**
	 * Language directory.
	 *
	 * @var LanguageManager
	 */
	private LanguageManager $languages;

	/**
	 * URL filter that prepends /<lang>/ to permalinks.
	 *
	 * @var LanguageUrlFilter
	 */
	private LanguageUrlFilter $url_filter;

	/**
	 * Construct the switcher.
	 *
	 * @param UrlRouter         $router     URL router.
	 * @param LanguageManager   $languages  Language directory.
	 * @param LanguageUrlFilter $url_filter URL filter for variant URLs.
	 */
	public function __construct( UrlRouter $router, LanguageManager $languages, LanguageUrlFilter $url_filter ) {
		$this->router     = $router;
		$this->languages  = $languages;
		$this->url_filter = $url_filter;
	}

	/**
	 * Register the [openpoly_language_switcher] shortcode.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_shortcode( 'openpoly_language_switcher', array( $this, 'shortcode' ) );
	}

	/**
	 * Shortcode handler.
	 *
	 * @param array<string, string> $atts User-supplied attributes.
	 * @return string
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'layout' => self::LAYOUT_LIST,
				'flags'  => '1',
			),
			$atts,
			'openpoly_language_switcher'
		);
		return $this->render( $atts['layout'], '1' === $atts['flags'] );
	}

	/**
	 * Render the switcher.
	 *
	 * Public for tests and template tags.
	 *
	 * @param string $layout One of the LAYOUT_* constants.
	 * @param bool   $show_flags Whether to show flag emoji.
	 * @return string
	 */
	public function render( string $layout = self::LAYOUT_LIST, bool $show_flags = true ): string {
		$current = $this->router->current_language();
		$request = $this->current_request_url();

		$items = array();
		foreach ( $this->languages->active_languages() as $lang ) {
			$code       = (string) $lang['code'];
			$native     = (string) $lang['native_name'];
			$flag       = (string) $lang['flag'];
			$is_current = null !== $current && $code === $current;

			$href    = $this->url_for( $code, $request, $current );
			$items[] = array(
				'code'       => $code,
				'native'     => $native,
				'flag'       => $flag,
				'href'       => $href,
				'is_current' => $is_current,
			);
		}

		switch ( $layout ) {
			case self::LAYOUT_DROPDOWN:
				return $this->render_dropdown( $items, $show_flags );
			case self::LAYOUT_INLINE:
				return $this->render_inline( $items, $show_flags );
			case self::LAYOUT_LIST:
			default:
				return $this->render_list( $items, $show_flags );
		}
	}

	/**
	 * Render as a <ul>.
	 *
	 * @param array<int, array<string, mixed>> $items      Language items to render.
	 * @param bool                             $show_flags Whether to prepend flag emoji.
	 * @return string
	 */
	private function render_list( array $items, bool $show_flags ): string {
		$out = '<ul class="openpoly-switcher">';
		foreach ( $items as $i ) {
			$out .= $this->render_link( $i, $show_flags, 'li' );
		}
		$out .= '</ul>';
		return $out;
	}

	/**
	 * Render as a comma-separated inline list.
	 *
	 * @param array<int, array<string, mixed>> $items      Language items to render.
	 * @param bool                             $show_flags Whether to prepend flag emoji.
	 * @return string
	 */
	private function render_inline( array $items, bool $show_flags ): string {
		$out   = '<span class="openpoly-switcher-inline">';
		$first = true;
		foreach ( $items as $i ) {
			if ( ! $first ) {
				$out .= ' | ';
			}
			$out  .= $this->render_link( $i, $show_flags, 'span' );
			$first = false;
		}
		$out .= '</span>';
		return $out;
	}

	/**
	 * Render as a <select> with JS onchange redirect.
	 *
	 * @param array<int, array<string, mixed>> $items      Language items to render.
	 * @param bool                             $show_flags Whether to prepend flag emoji.
	 * @return string
	 */
	private function render_dropdown( array $items, bool $show_flags ): string {
		$out = '<select class="openpoly-switcher-dropdown" onchange="window.location.href=this.value">';
		foreach ( $items as $i ) {
			$label    = ( $show_flags && '' !== $i['flag'] ? $i['flag'] . ' ' : '' ) . $i['native'];
			$selected = $i['is_current'] ? ' selected' : '';
			$out     .= sprintf(
				'<option value="%s"%s>%s</option>',
				esc_url( $i['href'] ),
				$selected,
				esc_html( $label )
			);
		}
		$out .= '</select>';
		return $out;
	}

	/**
	 * Render a single language link.
	 *
	 * @param array<string, mixed> $item       One language entry.
	 * @param bool                 $show_flags Whether to prepend flag emoji.
	 * @param string               $tag        HTML tag to wrap the link in.
	 * @return string
	 */
	private function render_link( array $item, bool $show_flags, string $tag ): string {
		$label = ( $show_flags && '' !== $item['flag'] ? $item['flag'] . ' ' : '' ) . $item['native'];
		$aria  = $item['is_current'] ? ' aria-current="true"' : '';
		$class = $item['is_current'] ? ' class="openpoly-current"' : '';
		return sprintf(
			'<%s><a href="%s"%s%s>%s</a></%s>',
			$tag,
			esc_url( $item['href'] ),
			$class,
			$aria,
			esc_html( $label ),
			$tag
		);
	}

	/**
	 * Build the target URL for one language variant.
	 *
	 * @param string      $code        Target language code.
	 * @param string      $request_url Current request URL.
	 * @param string|null $current     Current request language, or null.
	 * @return string Filtered URL for the language variant.
	 */
	private function url_for( string $code, string $request_url, ?string $current ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $current reserved for future canonical-url comparisons.
		// Save current router language, swap, ask the URL filter, restore.
		$previous = $this->router->current_language();
		$this->router->set_current_language( $code );
		try {
			return $this->url_filter->add_language_to_url( $request_url );
		} finally {
			if ( null === $previous ) {
				// Reset by reflection: M-09 left a public setter (M-14
				// gives us set_current_language; no reflection needed
				// any more).
				$ref = new \ReflectionClass( $this->router );
				$p   = $ref->getProperty( 'current_language' );
				$p->setAccessible( true );
				$p->setValue( $this->router, null );
			} else {
				$this->router->set_current_language( $previous );
			}
		}
	}

	/**
	 * Return the current request URL, with a best-effort fallback
	 * to home_url() when not available (test environment).
	 *
	 * @return string
	 */
	private function current_request_url(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display of the current URL.
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$path   = (string) wp_unslash( $_SERVER['REQUEST_URI'] );
			$host   = isset( $_SERVER['HTTP_HOST'] ) ? (string) wp_unslash( $_SERVER['HTTP_HOST'] ) : 'example.com';
			$scheme = is_ssl() ? 'https' : 'http';
			return $scheme . '://' . $host . $path;
		}
		if ( function_exists( 'home_url' ) ) {
			return (string) home_url( '/' );
		}
		return '/';
	}
}
