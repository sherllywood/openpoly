<?php
/**
 * Scans PHP files for gettext calls and registers them as
 * OpenPoly translatable strings.
 *
 * Covers: __(), _e(), _x(), _ex(), _n(), _nx()
 *
 * Each found string is inserted into op_strings with its
 * domain, context, and a unique fingerprint so the same
 * string is not registered twice.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Strings;

defined( 'ABSPATH' ) || exit;

/**
 * Extracts gettext strings from PHP source code.
 *
 * @since 0.5.0-dev
 */
final class GettextScanner {

	/**
	 * Regex that captures gettext-style calls.
	 *
	 * Groups: 1=function 2=string 3=context(optional) 4=domain 5=plural
	 */
	private const GETTEXT_REGEX = '/
		(?P<fn>__|_e|_x|_ex|_n|_nx)\s*\(\s*
		[\'"](?P<text>(?:[^\'"]|\\[\'"])*)[\'"]\s*
		(?:,\s*[\'"](?P<context>(?:[^\'"]|\\\\[\'"])*)[\'"]\s*)?
		(?:,\s*[\'"](?P<domain>(?:[^\'"]|\\\\[\'"])*)[\'"]\s*)?
		(?:,\s*[\'"](?P<plural>(?:[^\'"]|\\\\[\'"])*)[\'"]\s*)?
		\)/sx';

	/**
	 * File extensions to scan.
	 */
	private const EXTENSIONS = array( 'php' );

	/**
	 * Scan a single file and return found strings.
	 *
	 * @param string $file_path Absolute path to a .php file.
	 * @return array<int, array{text:string, context:string, domain:string, plural:string, fn:string}>
	 */
	public function scan_file( string $file_path ): array {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return array();
		}

		$ext = pathinfo( $file_path, PATHINFO_EXTENSION );
		if ( ! in_array( $ext, self::EXTENSIONS, true ) ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file scan, not remote.
		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			return array();
		}

		return $this->parse( $content );
	}

	/**
	 * Scan a directory recursively.
	 *
	 * @param string $directory Base directory path.
	 * @return array<int, array{text:string, context:string, domain:string, plural:string, fn:string}>
	 */
	public function scan_directory( string $directory ): array {
		if ( ! is_dir( $directory ) ) {
			return array();
		}

		$results = array();
		$dir     = new \RecursiveDirectoryIterator( $directory );
		$iter    = new \RecursiveIteratorIterator( $dir );

		foreach ( $iter as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}
			if ( ! in_array( $file->getExtension(), self::EXTENSIONS, true ) ) {
				continue;
			}
			$results = array_merge( $results, $this->scan_file( $file->getPathname() ) );
		}

		return $results;
	}

	/**
	 * Parse PHP source code and extract gettext calls.
	 *
	 * @param string $code PHP source code.
	 * @return array<int, array{text:string, context:string, domain:string, plural:string, fn:string}>
	 */
	public function parse( string $code ): array {
		$results = array();
		$count   = preg_match_all( self::GETTEXT_REGEX, $code, $matches, PREG_SET_ORDER );

		if ( false === $count || 0 === $count ) {
			return $results;
		}

		foreach ( $matches as $m ) {
			$domain  = isset( $m['domain'] ) && '' !== $m['domain'] ? $m['domain'] : 'default';
			$context = isset( $m['context'] ) && '' !== $m['context'] ? $m['context'] : '';
			$text    = $m['text'];
			$plural  = isset( $m['plural'] ) && '' !== $m['plural'] ? $m['plural'] : '';
			$fn      = $m['fn'];

			$results[] = array(
				'text'    => $text,
				'context' => $context,
				'domain'  => $domain,
				'plural'  => $plural,
				'fn'      => $fn,
			);
		}

		return $results;
	}
}
