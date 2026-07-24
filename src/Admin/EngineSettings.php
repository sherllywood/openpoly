<?php
/**
 * Engine settings admin page.
 *
 * Allows the site admin to configure the translation engine:
 *   - Gateway (official OpenPoly) or Custom endpoint.
 *   - API token.
 *   - Custom base URL and model.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Admin;

use OpenPoly\Engine\OpenAiCompatibleEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Engine settings page.
 *
 * @since 1.0.0-dev
 */
final class EngineSettings {

	/**
	 * Settings page slug.
	 */
	private const PAGE_SLUG = 'openpoly-engine';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_post_openpoly_engine_save', array( $this, 'handle_save' ) );
	}

	/**
	 * Register the settings sub-page under Translations.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_submenu_page(
			'openpoly-ate',
			esc_html__( 'Engine Settings', 'openpoly' ),
			esc_html__( 'Engine', 'openpoly' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render(): void {
		$engine_type = get_option( 'openpoly_engine_type', 'gateway' );
		$api_key     = get_option( 'openpoly_engine_api_key', '' );
		$base_url    = get_option( 'openpoly_engine_base_url', 'https://gateway.openpoly.example/v1/' );
		$model       = get_option( 'openpoly_engine_model', '' );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only.
		$saved = isset( $_GET['saved'] );
		// phpcs:enable

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Engine Settings', 'openpoly' ) . '</h1>';

		if ( $saved ) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Settings saved.', 'openpoly' )
				. '</p></div>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'openpoly_engine_save', 'openpoly_engine_nonce' );
		echo '<input type="hidden" name="action" value="openpoly_engine_save">';

		echo '<table class="form-table">';

		// Engine type.
		echo '<tr>';
		echo '<th scope="row"><label for="openpoly_engine_type">' . esc_html__( 'Engine', 'openpoly' ) . '</label></th>';
		echo '<td>';
		echo '<select name="engine_type" id="openpoly_engine_type">';
		echo '<option value="gateway"' . selected( $engine_type, 'gateway', false ) . '>'
			. esc_html__( 'OpenPoly Gateway (official)', 'openpoly' ) . '</option>';
		echo '<option value="custom"' . selected( $engine_type, 'custom', false ) . '>'
			. esc_html__( 'Custom OpenAI-compatible endpoint', 'openpoly' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Custom endpoints bypass the official gateway. Quota tracking and billing are not available.', 'openpoly' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		// API key.
		echo '<tr>';
		echo '<th scope="row"><label for="openpoly_engine_api_key">' . esc_html__( 'API Token', 'openpoly' ) . '</label></th>';
		echo '<td>';
		echo '<input type="password" name="api_key" id="openpoly_engine_api_key" value="' . esc_attr( $api_key ) . '" class="regular-text" autocomplete="off">';
		echo '<p class="description">' . esc_html__( 'Your OpenPoly customer token or custom API key.', 'openpoly' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Custom fields.
		echo '<tr class="openpoly-custom-row"' . ( 'custom' !== $engine_type ? ' style="display:none;"' : '' ) . '>';
		echo '<th scope="row"><label for="openpoly_engine_base_url">' . esc_html__( 'Base URL', 'openpoly' ) . '</label></th>';
		echo '<td>';
		echo '<input type="url" name="base_url" id="openpoly_engine_base_url" value="' . esc_attr( $base_url ) . '" class="regular-text">';
		echo '</td>';
		echo '</tr>';

		echo '<tr class="openpoly-custom-row"' . ( 'custom' !== $engine_type ? ' style="display:none;"' : '' ) . '>';
		echo '<th scope="row"><label for="openpoly_engine_model">' . esc_html__( 'Model', 'openpoly' ) . '</label></th>';
		echo '<td>';
		echo '<input type="text" name="model" id="openpoly_engine_model" value="' . esc_attr( $model ) . '" class="regular-text">';
		echo '<p class="description">' . esc_html__( 'e.g. gpt-4o, deepseek-chat. Leave empty for default.', 'openpoly' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		echo '</table>';

		// JavaScript to toggle custom rows.
		echo '<script>
		document.getElementById("openpoly_engine_type").addEventListener("change", function() {
			var rows = document.querySelectorAll(".openpoly-custom-row");
			rows.forEach(function(r) { r.style.display = this.value === "custom" ? "" : "none"; });
		}.bind(document.getElementById("openpoly_engine_type")));
		</script>';

		echo '<p class="submit">';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Save Changes', 'openpoly' ) . '</button>';
		echo '</p>';
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Handle settings save.
	 *
	 * @return void
	 */
	public function handle_save(): void {
		if ( ! isset( $_POST['openpoly_engine_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['openpoly_engine_nonce'] ) ), 'openpoly_engine_save' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'openpoly' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'openpoly' ) );
		}

		$engine_type = isset( $_POST['engine_type'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['engine_type'] ) ) : 'gateway';
		$api_key     = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['api_key'] ) ) : '';
		$base_url    = isset( $_POST['base_url'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['base_url'] ) ) : '';
		$model       = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['model'] ) ) : '';

		update_option( 'openpoly_engine_type', $engine_type );
		update_option( 'openpoly_engine_api_key', $api_key );
		update_option( 'openpoly_engine_base_url', $base_url );
		update_option( 'openpoly_engine_model', $model );

		wp_safe_redirect(
			add_query_arg(
				array( 'saved' => '1' ),
				admin_url( 'admin.php?page=' . self::PAGE_SLUG )
			)
		);
		exit;
	}
}
