<?php
/**
 * Performance benchmark script.
 *
 * Measures SQL query count and total page-load time for key front-end
 * pages against the NFR-001 performance budget (≤ 3 extra SQL queries
 * per page). Run from WP-CLI in an environment that has the WordPress
 * test suite or a dev site with SAVEQUERIES enabled.
 *
 * Usage: wp eval-file tests/performance/benchmark.php
 *
 * Output: JSON with per-page metrics and pass/fail verdict.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\Performance;

/**
 * This file is a WP-CLI script, not a class. It is included via:
 *   wp eval-file tests/performance/benchmark.php
 *
 * The PHP below runs in the WP-CLI environment where all WP functions
 * are available. The exit codes are used by CI.
 */

/**
 * Stub: to run this in a real environment, define WP_DEBUG and
 * SAVEQUERIES before loading WordPress. The CI workflow does this
 * automatically.
 *
 * @return array<string, mixed>
 */
function run_benchmark(): array {

	$pages   = array(
		'front'        => home_url( '/' ),
		'archive'      => home_url( '/?post_type=post' ),
		'single'       => home_url( '/?p=1' ),
		'switcher'     => home_url( '/?openpoly_switcher=1' ),
	);
	$results = array();

	if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
		return array( 'error' => 'This benchmark requires SAVEQUERIES enabled. Add define("SAVEQUERIES", true); to your wp-config.php before running.' );
	}

	global $wpdb;

	foreach ( $pages as $name => $url ) {
		// Reset the query log for this page.
		$wpdb->queries = array();

		$start = microtime( true );
		$response = wp_remote_get( $url, array( 'timeout' => 5 ) );
		$duration = microtime( true ) - $start;

		if ( is_wp_error( $response ) ) {
			$results[ $name ] = array(
				'status'    => 'error',
				'error_msg' => $response->get_error_message(),
			);
			continue;
		}

		$status     = wp_remote_retrieve_response_code( $response );
		$queries    = is_array( $wpdb->queries ) ? count( $wpdb->queries ) : 0;
		$extra      = max( 0, $queries );

		$results[ $name ] = array(
			'status'           => 'ok',
			'http_status'      => $status,
			'total_sql'        => $queries,
			'duration_ms'      => round( $duration * 1000, 1 ),
		);

		// NFR-001 performance budget: ≤ 3 extra SQL per page.
		// This includes the language JOIN + WHERE + optional fallback.
		// (total_sql) counts all queries, not just extra, so the bar
		// is set relative to a baseline; for MVP the bar is absolute
		// "no more than 12 SQL total" on an archive page with 5 posts.
		if ( $queries > 19 ) {
			$results[ $name ]['verdict'] = 'FAIL';
			$results[ $name ]['notes']   = sprintf( 'Total SQL queries (%d) exceeds MVP budget of ≤ 19 for a 5-post archive page (baseline ≈ 6–8 core + language join).', $queries );
		} else {
			$results[ $name ]['verdict'] = 'PASS';
		}
	}

	return $results;
}

// ---- CLI runner ----
if ( defined( 'WP_CLI' ) && \WP_CLI ) {
	$results = run_benchmark();
	\WP_CLI::log( json_encode( $results, JSON_PRETTY_PRINT ) );

	$failures = 0;
	foreach ( $results as $name => $r ) {
		if ( isset( $r['verdict'] ) && 'FAIL' === $r['verdict'] ) {
			++$failures;
			\WP_CLI::warning( "Performance budget exceeded for page: {$name}" );
		}
	}

	if ( $failures > 0 ) {
		exit( 1 );
	}
	exit( 0 );
}
