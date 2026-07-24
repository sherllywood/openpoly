<?php
/**
 * ATE (Advanced Translation Editor) — segment-based translation page.
 *
 * Renders a WordPress admin page where translators can work on
 * individual segments (sentences) of a post.
 *
 * Features:
 *   - Auto-segments content on first open.
 *   - Shows source segment + target textarea side by side.
 *   - Save / XLIFF-export / XLIFF-import buttons.
 *   - Progress bar (untranslated / draft / translated counts).
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Admin;

use OpenPoly\Segmenter\Segmenter;
use OpenPoly\Segmenter\SegmentRepository;
use OpenPoly\Segmenter\XliffExport;
use OpenPoly\Segmenter\XliffImport;
use OpenPoly\Translation\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * ATE editor admin page.
 *
 * @since 1.0.0-dev
 */
final class AteEditor {

	/**
	 * Page slug.
	 */
	private const PAGE_SLUG = 'openpoly-ate';

	/**
	 * Segmenter engine.
	 *
	 * @var Segmenter
	 */
	private Segmenter $segmenter;

	/**
	 * Segment data-access object.
	 *
	 * @var SegmentRepository
	 */
	private SegmentRepository $segments;

	/**
	 * Translation group repository.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * XLIFF export handler.
	 *
	 * @var XliffExport
	 */
	private XliffExport $xliff_export;

	/**
	 * XLIFF import handler.
	 *
	 * @var XliffImport
	 */
	private XliffImport $xliff_import;

	/**
	 * Constructor.
	 *
	 * @param Segmenter        $segmenter    Segmenter engine.
	 * @param SegmentRepository $segments    Segment data-access object.
	 * @param Repository        $repository   Translation group repository.
	 * @param XliffExport       $xliff_export XLIFF export handler.
	 * @param XliffImport       $xliff_import XLIFF import handler.
	 */
	public function __construct(
		Segmenter $segmenter,
		SegmentRepository $segments,
		Repository $repository,
		XliffExport $xliff_export,
		XliffImport $xliff_import
	) {
		$this->segmenter    = $segmenter;
		$this->segments     = $segments;
		$this->repository   = $repository;
		$this->xliff_export  = $xliff_export;
		$this->xliff_import  = $xliff_import;
	}

	/**
	 * Register hooks (admin menu + AJAX handlers).
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_openpoly_ate_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_openpoly_ate_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_openpoly_ate_import', array( $this, 'handle_import' ) );
	}

	/**
	 * Register the admin menu page.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		add_menu_page(
			esc_html__( 'Translation Editor', 'openpoly' ),
			esc_html__( 'Translations', 'openpoly' ),
			'edit_posts',
			self::PAGE_SLUG,
			array( $this, 'render' ),
			'dashicons-translation',
			58
		);
	}

	/**
	 * Render the ATE editor page.
	 *
	 * @return void
	 */
	public function render(): void {
		$trid      = isset( $_GET['trid'] ) ? (int) $_GET['trid'] : 0;
		$lang_code = isset( $_GET['lang'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['lang'] ) ) : '';
		$action    = isset( $_GET['a'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['a'] ) ) : '';
		$saved     = isset( $_GET['saved'] );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Translation Editor', 'openpoly' ) . '</h1>';

		if ( 0 === $trid || '' === $lang_code ) {
			$this->render_selector();
			echo '</div>';
			return;
		}

		if ( 'segment' === $action ) {
			$this->do_segment( $trid, $lang_code );
		}

		if ( $saved ) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Translations saved.', 'openpoly' )
				. '</p></div>';
		}

		$this->render_editor( $trid, $lang_code );
		echo '</div>';
	}

	/**
	 * Render a simple post/language selector when no trid is given.
	 *
	 * @return void
	 */
	private function render_selector(): void {
		global $wpdb;

		// List posts that have a translation group.
		$rows = $wpdb->get_results(
			"SELECT DISTINCT t.trid, t.element_type, t.element_id, p.post_title, p.post_type
			 FROM {$wpdb->prefix}op_translations t
			 LEFT JOIN {$wpdb->prefix}posts p ON p.ID = t.element_id AND t.element_type LIKE 'post\_%'
			 WHERE t.element_type LIKE 'post\_%'
			 ORDER BY p.post_title ASC
			 LIMIT 50",
			ARRAY_A
		);

		echo '<p>' . esc_html__( 'Select a post to translate:', 'openpoly' ) . '</p>';

		if ( empty( $rows ) ) {
			echo '<div class="notice notice-info"><p>'
				. esc_html__( 'No translatable posts found. Create a post and assign a language first.', 'openpoly' )
				. '</p></div>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>' . esc_html__( 'Post', 'openpoly' ) . '</th><th>' . esc_html__( 'Type', 'openpoly' ) . '</th><th>' . esc_html__( 'Languages', 'openpoly' ) . '</th></tr></thead>';
		echo '<tbody>';

		$shown_trids = array();
		foreach ( $rows as $row ) {
			$trid = (int) $row['trid'];
			if ( in_array( $trid, $shown_trids, true ) ) {
				continue;
			}
			$shown_trids[] = $trid;

			$title = $row['post_title'] ?? '(untitled)';
			$type  = $row['post_type'] ?? 'post';

			// Get target languages for this trid.
			$langs = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT language_code FROM {$wpdb->prefix}op_translations WHERE trid = %d AND source_language_code IS NOT NULL",
					$trid
				)
			);

			$links = array();
			if ( is_array( $langs ) ) {
				foreach ( $langs as $lc ) {
					$url      = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&trid=' . $trid . '&lang=' . urlencode( $lc ) . '&a=segment' );
					$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $lc ) . '</a>';
				}
			}

			echo '<tr>';
			echo '<td><strong>' . esc_html( $title ) . '</strong></td>';
			echo '<td>' . esc_html( $type ) . '</td>';
			echo '<td>' . wp_kses_post( implode( ' | ', $links ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Run the segmenter on a post's content (first visit to editor).
	 *
	 * @param int    $trid      Translation group id.
	 * @param string $lang_code Target language code.
	 * @return void
	 */
	private function do_segment( int $trid, string $lang_code ): void {
		// Find the source element for this trid.
		global $wpdb;
		$source = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT element_type, element_id FROM {$wpdb->prefix}op_translations
				 WHERE trid = %d AND source_language_code IS NULL",
				$trid
			),
			ARRAY_A
		);

		if ( ! is_array( $source ) ) {
			return;
		}

		$element_type = (string) $source['element_type'];
		$element_id   = (int) $source['element_id'];

		// Fetch post content.
		$post = get_post( $element_id );
		if ( null === $post ) {
			return;
		}

		// Only segment if no segments exist yet for this (trid, lang).
		$existing = $this->segments->load( $trid, $lang_code );
		if ( array() !== $existing ) {
			return;
		}

		$content  = $post->post_content;
		$segs     = $this->segmenter->segment( $content );

		$to_save = array();
		foreach ( $segs as $i => $seg ) {
			$to_save[] = array(
				'segment_index' => $i,
				'source_text'   => $seg['text'],
				'md5'           => $seg['md5'],
			);
		}

		$this->segments->save( $trid, $element_type, $element_id, $lang_code, $to_save );
	}

	/**
	 * Render the segment editor form.
	 *
	 * @param int    $trid      Translation group id.
	 * @param string $lang_code Target language code.
	 * @return void
	 */
	private function render_editor( int $trid, string $lang_code ): void {
		$segments = $this->segments->load( $trid, $lang_code );

		if ( array() === $segments ) {
			echo '<div class="notice notice-warning"><p>'
				. esc_html__( 'No segments found. Make sure the post has content.', 'openpoly' )
				. '</p></div>';
			return;
		}

		$counts = $this->segments->count_by_status( $trid, $lang_code );
		$pct    = $counts['total'] > 0 ? round( ( $counts['translated'] / $counts['total'] ) * 100 ) : 0;

		// Progress bar.
		echo '<div class="openpoly-ate-progress" style="margin: 12px 0;">';
		echo '<div style="background:#ddd; border-radius:4px; height:20px; max-width:400px;">';
		echo '<div style="background:#46b450; height:20px; width:' . (int) $pct . '%; border-radius:4px;"></div>';
		echo '</div>';
		echo '<p style="margin:4px 0;">'
			. (int) $pct . '% '
			. esc_html__( 'translated', 'openpoly' )
			. ' (' . (int) $counts['total'] . ' ' . esc_html__( 'segments', 'openpoly' )
			. ': ' . (int) $counts['translated'] . ' ' . esc_html__( 'done', 'openpoly' )
			. ', ' . (int) $counts['draft'] . ' ' . esc_html__( 'draft', 'openpoly' )
			. ', ' . (int) $counts['untranslated'] . ' ' . esc_html__( 'pending', 'openpoly' ) . ')'
			. '</p>';
		echo '</div>';

		// Action buttons.
		$base_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&trid=' . $trid . '&lang=' . urlencode( $lang_code ) );
		echo '<p style="margin-bottom:16px;">';
		echo '<a class="button button-primary" href="' . esc_url( $base_url . '&a=export' ) . '">'
			. esc_html__( 'Export XLIFF', 'openpoly' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( $base_url . '&a=import-form' ) . '">'
			. esc_html__( 'Import XLIFF', 'openpoly' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">'
			. esc_html__( '← Back', 'openpoly' ) . '</a>';
		echo '</p>';

		// Import form (shown when a=import-form).
		if ( isset( $_GET['a'] ) && 'import-form' === $_GET['a'] ) {
			$this->render_import_form( $trid, $lang_code );
		}

		// Segment table + form.
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'openpoly_ate_save', 'openpoly_ate_nonce' );
		echo '<input type="hidden" name="action" value="openpoly_ate_save">';
		echo '<input type="hidden" name="trid" value="' . (int) $trid . '">';
		echo '<input type="hidden" name="lang" value="' . esc_attr( $lang_code ) . '">';

		echo '<table class="wp-list-table widefat fixed striped" style="table-layout:auto;">';
		echo '<thead><tr>'
			. '<th style="width:5%;">#</th>'
			. '<th style="width:45%;">' . esc_html__( 'Source', 'openpoly' ) . '</th>'
			. '<th style="width:45%;">' . esc_html__( 'Translation', 'openpoly' ) . '</th>'
			. '<th style="width:5%;">' . esc_html__( 'Status', 'openpoly' ) . '</th>'
			. '</tr></thead>';
		echo '<tbody>';

		foreach ( $segments as $seg ) {
			$sid    = (int) $seg['id'];
			$idx    = (int) ( $seg['segment_index'] ?? 0 );
			$source = (string) ( $seg['source_text'] ?? '' );
			$target = (string) ( $seg['translated_text'] ?? '' );
			$status = (int) ( $seg['status'] ?? 0 );
			$needs  = (int) ( $seg['needs_update'] ?? 0 );

			$status_label = $this->status_label( $status, $needs );

			echo '<tr>';
			echo '<td>' . ( $idx + 1 ) . '</td>';
			echo '<td><div style="max-height:120px; overflow-y:auto; white-space:pre-wrap; font-size:13px;">' . esc_html( $source ) . '</div></td>';
			echo '<td>';
			echo '<textarea name="seg[' . (int) $sid . ']" rows="3" style="width:100%; font-size:13px;">' . esc_textarea( $target ) . '</textarea>';
			echo '<input type="hidden" name="seg_status[' . (int) $sid . ']" value="' . (int) $status . '">';
			echo '</td>';
			echo '<td>' . esc_html( $status_label ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		echo '<p class="submit">';
		echo '<button type="submit" name="save" class="button button-primary">'
			. esc_html__( 'Save Translations', 'openpoly' ) . '</button> ';
		echo '<button type="submit" name="save_draft" class="button">'
			. esc_html__( 'Save as Draft', 'openpoly' ) . '</button>';
		echo '</p>';

		echo '</form>';
	}

	/**
	 * Render the XLIFF import form.
	 *
	 * @param int    $trid      Translation group id.
	 * @param string $lang_code Target language code.
	 * @return void
	 */
	private function render_import_form( int $trid, string $lang_code ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data" style="margin-bottom:16px; padding:12px; background:#f9f9f9; border:1px solid #ccd0d4;">';
		wp_nonce_field( 'openpoly_ate_import', 'openpoly_ate_import_nonce' );
		echo '<input type="hidden" name="action" value="openpoly_ate_import">';
		echo '<input type="hidden" name="trid" value="' . (int) $trid . '">';
		echo '<input type="hidden" name="lang" value="' . esc_attr( $lang_code ) . '">';
		echo '<h3>' . esc_html__( 'Import XLIFF File', 'openpoly' ) . '</h3>';
		echo '<p>' . esc_html__( 'Select an XLIFF 2.0 (.xliff) file to import translations.', 'openpoly' ) . '</p>';
		echo '<input type="file" name="xliff_file" accept=".xliff,.xml"> ';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Upload & Import', 'openpoly' ) . '</button>';
		echo '</form>';
	}

	/**
	 * Handle save.
	 *
	 * @return void
	 */
	public function handle_save(): void {
		if ( ! isset( $_POST['openpoly_ate_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['openpoly_ate_nonce'] ) ), 'openpoly_ate_save' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'openpoly' ) );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'openpoly' ) );
		}

		$trid      = isset( $_POST['trid'] ) ? (int) $_POST['trid'] : 0;
		$lang_code = isset( $_POST['lang'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['lang'] ) ) : '';
		$segments  = isset( $_POST['seg'] ) ? wp_unslash( $_POST['seg'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per-item below.
		$is_draft  = isset( $_POST['save_draft'] );

		if ( ! is_array( $segments ) ) {
			$segments = array();
		}

		$status = $is_draft ? 1 : 2;

		foreach ( $segments as $id => $text ) {
			$id   = (int) $id;
			$text = sanitize_textarea_field( (string) $text );
			if ( $id > 0 && '' !== $text ) {
				$this->segments->update_translation( $id, $text, $status, (int) get_current_user_id() );
			}
		}

		// Redirect back to editor.
		wp_safe_redirect(
			add_query_arg(
				array( 'saved' => '1' ),
				admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&trid=' . $trid . '&lang=' . urlencode( $lang_code ) )
			)
		);
		exit;
	}

	/**
	 * Handle XLIFF export.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'openpoly' ) );
		}

		$trid      = isset( $_GET['trid'] ) ? (int) $_GET['trid'] : 0;
		$lang_code = isset( $_GET['lang'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['lang'] ) ) : '';

		// Determine source language from the trid.
		$source_lang = $this->get_source_language( $trid );
		$segments    = $this->segments->load( $trid, $lang_code );

		$xml = $this->xliff_export->export(
			$source_lang ?? 'en_US',
			$lang_code,
			$segments,
			array( 'trid' => (string) $trid )
		);

		header( 'Content-Type: application/xliff+xml; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="openpoly-trid-' . (int) $trid . '-' . esc_attr( $lang_code ) . '.xliff"' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML output, intentional.
		echo $xml;
		exit;
	}

	/**
	 * Handle XLIFF import (file upload).
	 *
	 * @return void
	 */
	public function handle_import(): void {
		if ( ! isset( $_POST['openpoly_ate_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['openpoly_ate_import_nonce'] ) ), 'openpoly_ate_import' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'openpoly' ) );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'openpoly' ) );
		}

		$trid      = isset( $_POST['trid'] ) ? (int) $_POST['trid'] : 0;
		$lang_code = isset( $_POST['lang'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['lang'] ) ) : '';

		if ( ! isset( $_FILES['xliff_file'] ) || UPLOAD_ERR_OK !== $_FILES['xliff_file']['error'] ) {
			wp_die( esc_html__( 'File upload failed.', 'openpoly' ) );
		}

		$content = file_get_contents( $_FILES['xliff_file']['tmp_name'] );
		if ( false === $content ) {
			wp_die( esc_html__( 'Could not read uploaded file.', 'openpoly' ) );
		}

		$units = $this->xliff_import->parse( $content );
		$count = $this->xliff_import->apply( $this->segments, $units );

		wp_safe_redirect(
			add_query_arg(
				array( 'saved' => '1', 'imported' => (string) $count ),
				admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&trid=' . $trid . '&lang=' . urlencode( $lang_code ) )
			)
		);
		exit;
	}

	/**
	 * Get the source language code for a trid.
	 *
	 * @param int $trid Translation group id.
	 * @return string|null
	 */
	private function get_source_language( int $trid ): ?string {
		global $wpdb;
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT language_code FROM {$wpdb->prefix}op_translations
				 WHERE trid = %d AND source_language_code IS NULL",
				$trid
			)
		);
	}

	/**
	 * Return a human-readable status label.
	 *
	 * @param int $status Status code.
	 * @param int $needs  Needs-update flag.
	 * @return string
	 */
	private function status_label( int $status, int $needs ): string {
		if ( 1 === $needs ) {
			return _x( 'Needs Update', 'segment status', 'openpoly' );
		}
		switch ( $status ) {
			case 10:
				return _x( 'Approved', 'segment status', 'openpoly' );
			case 2:
				return _x( 'Translated', 'segment status', 'openpoly' );
			case 1:
				return _x( 'Draft', 'segment status', 'openpoly' );
			default:
				return _x( 'Untranslated', 'segment status', 'openpoly' );
		}
	}
}
