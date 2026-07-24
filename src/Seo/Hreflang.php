<?php
/**
 * Hreflang output.
 *
 * Emits one <link rel="alternate" hreflang="..." href="..."> tag
 * per language variant of the current post, plus an x-default
 * pointing at the default language. Mounted on wp_head by
 * HreflangServiceProvider.
 *
 * SEO: the absolute href is computed by language_url_filter (M-09)
 * so the URL is the canonical /<lang>/... form for each variant.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Seo;

use OpenPoly\Language\LanguageManager;
use OpenPoly\Translation\TranslationGroup;
use OpenPoly\Url\LanguageUrlFilter;
use OpenPoly\Url\UrlRouter;

defined( 'ABSPATH' ) || exit;

/**
 * Pure helper that builds the hreflang link tags for a translation
 * group. Output is a list of arrays suitable for <link> rendering.
 *
 * @since 0.5.0-dev
 */
final class Hreflang {

	/**
	 * URL router used to swap current language per variant.
	 *
	 * @var UrlRouter
	 */
	private UrlRouter $router;

	/**
	 * Language directory used to enumerate variants and read the default.
	 *
	 * @var LanguageManager
	 */
	private LanguageManager $languages;

	/**
	 * Filter that prepends /<lang>/ to a permalink.
	 *
	 * @var LanguageUrlFilter
	 */
	private LanguageUrlFilter $url_filter;

	/**
	 * Construct the hreflang renderer.
	 *
	 * @param UrlRouter         $router     URL router (current request language).
	 * @param LanguageManager   $languages  Language directory.
	 * @param LanguageUrlFilter $url_filter Filter that adds /<lang>/ to URLs.
	 */
	public function __construct( UrlRouter $router, LanguageManager $languages, LanguageUrlFilter $url_filter ) {
		$this->router     = $router;
		$this->languages  = $languages;
		$this->url_filter = $url_filter;
	}

	/**
	 * Register the wp_head hook.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_head', array( $this, 'render' ), 1 );
	}

	/**
	 * Print the hreflang <link> tags into the current page head.
	 *
	 * Eagerly returns on non-singular views; only single-post /
	 * single-page requests get hreflang output.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$links = $this->build_links( $post );
		foreach ( $links as $link ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- short-circuit escaping; values are pre-escaped below.
			echo '<link rel="alternate" hreflang="' . esc_attr( $link['hreflang'] ) . '" href="' . esc_url( $link['href'] ) . '" />' . "\n";
		}
	}

	/**
	 * Build the link entries for one post.
	 *
	 * Public so tests can drive the logic without booting WordPress.
	 *
	 * @param \WP_Post $post The post whose translation group drives the links.
	 * @return array<int, array{hreflang:string, href:string}>
	 */
	public function build_links( $post ): array {
		$links = array();

		$element_type = 'post_' . $post->post_type;
		$trid         = $this->resolve_trid( $element_type, (int) $post->ID );
		if ( null === $trid ) {
			return $links;
		}

		// Force the router to consider this post's language when
		// generating URLs. Without this, the URL filter would
		// emit the wrong prefix for variants.
		$group = $this->resolve_group( $trid );
		if ( null === $group ) {
			return $links;
		}

		$default_code = $this->languages->default_language_code();
		$emitted_xdef = false;

		foreach ( $this->languages->active_languages() as $lang ) {
			$code       = (string) $lang['code'];
			$hreflang   = '' !== (string) $lang['hreflang'] ? (string) $lang['hreflang'] : $code;
			$element_id = $group->get( $code );
			if ( null === $element_id ) {
				// Language is active but the current post has no
				// variant in it; we do not emit a link for that
				// language because the URL would 404. Future M-XX
				// could add fallback rendering.
				continue;
			}

			$url = $this->url_for_element( $element_id, $code, $post );
			if ( null === $url ) {
				continue;
			}

			$links[] = array(
				'hreflang' => $hreflang,
				'href'     => $url,
			);

			if ( null !== $default_code && $default_code === $code ) {
				$emitted_xdef = true;
			}
		}

		// x-default: always emit, pointing at the default language
		// URL of the source post. Google uses x-default as the
		// fallback for unmatched locales.
		if ( null !== $default_code ) {
			$default_element = $group->source();
			$default_url     = $this->url_for_element(
				null === $default_element ? (int) $post->ID : (int) $default_element,
				$default_code,
				$post
			);
			if ( null !== $default_url ) {
				$links[] = array(
					'hreflang' => 'x-default',
					'href'     => $default_url,
				);
			}
		} else {
			$emitted_xdef = false;
		}

		unset( $emitted_xdef ); // referenced in case future logic needs it.

		return $links;
	}

	/**
	 * Resolve the trid for an element. Stubbed here to avoid
	 * injecting the Translation\Repository into the SEO module;
	 * the production wiring is replaced by the HreflangServiceProvider.
	 *
	 * @param string $element_type Element type, e.g. "post_post".
	 * @param int    $element_id   Post id.
	 * @return int|null
	 */
	protected function resolve_trid( string $element_type, int $element_id ): ?int {
		// Lazy: hooked by HreflangServiceProvider via $this->trid_resolver.
		if ( null !== $this->trid_resolver ) {
			return ( $this->trid_resolver )( $element_type, $element_id );
		}
		return null;
	}

	/**
	 * Lazy-bound resolver injected by the service provider.
	 *
	 * @var (\Closure(string, int): (int|null))|null
	 */
	private $trid_resolver = null;

	/**
	 * Lazy-bound group loader injected by the service provider.
	 *
	 * @var (\Closure(int): (TranslationGroup|null))|null
	 */
	private $group_loader = null;

	/**
	 * Inject the trid resolver. Called by HreflangServiceProvider.
	 *
	 * @param callable(string, int): (int|null) $resolver Trid resolver taking (element_type, element_id).
	 * @return void
	 */
	public function set_trid_resolver( callable $resolver ): void {
		$this->trid_resolver = $resolver;
	}

	/**
	 * Inject the group loader. Called by HreflangServiceProvider.
	 *
	 * @param callable(int): (TranslationGroup|null) $loader Group loader taking a trid.
	 * @return void
	 */
	public function set_group_loader( callable $loader ): void {
		$this->group_loader = $loader;
	}

	/**
	 * Build the URL for an element, with the appropriate language
	 * prefix. The trick: we temporarily set the router's current
	 * language to the variant's code so that LanguageUrlFilter
	 * emits the right /<lang>/ prefix.
	 *
	 * @param int      $element_id Post id to fetch the permalink for.
	 * @param string   $code       Language code to apply during URL generation.
	 * @param \WP_Post $post      Original post (unused but kept for future fallback rendering).
	 * @return string|null
	 */
	private function url_for_element( int $element_id, string $code, \WP_Post $post ): ?string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $post reserved for future fallback rendering.
		// Temporarily switch the router so LanguageUrlFilter
		// emits the variant's prefix.
		$previous   = $this->router->current_language();
		$reflection = new \ReflectionClass( $this->router );
		$property   = $reflection->getProperty( 'current_language' );
		$property->setAccessible( true );
		$property->setValue( $this->router, $code );

		try {
			$url = get_permalink( $element_id );
			if ( false === $url ) {
				return null;
			}
			return $this->url_filter->add_language_to_url( (string) $url );
		} finally {
			$property->setValue( $this->router, $previous );
		}
	}

	/**
	 * Resolve the translation group for a trid.
	 *
	 * @param int $trid Translation group id.
	 * @return TranslationGroup|null
	 */
	private function resolve_group( int $trid ): ?TranslationGroup {
		if ( null !== $this->group_loader ) {
			$group = ( $this->group_loader )( $trid );
			return $group instanceof TranslationGroup ? $group : null;
		}
		return null;
	}
}
