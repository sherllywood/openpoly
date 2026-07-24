<?php
/**
 * First-run setup wizard.
 *
 * Renders a 4-step form that:
 *   1. Confirms the default language.
 *   2. Activates a set of target languages.
 *   3. Picks the URL structure (directory / parameter / domain).
 *   4. Shows a summary and writes the settings.
 *
 * State is persisted to the `openpoly_settings` option as it
 * advances, so the user can leave and come back.
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Setup;

use OpenPoly\Language\Catalog;
use OpenPoly\Language\LanguageManager;
use OpenPoly\Language\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and processes the first-run setup wizard.
 *
 * @since 0.5.0-dev
 */
final class SetupWizard {

	/**
	 * Option key for "wizard was skipped by the user".
	 */
	public const SKIPPED_OPTION = 'openpoly_wizard_skipped';

	/**
	 * Option key where final settings are persisted.
	 */
	public const SETTINGS_OPTION = 'openpoly_settings';

	/**
	 * Nonce action for step submissions.
	 */
	public const NONCE_ACTION = 'openpoly_setup_wizard';

	/**
	 * Language directory used to enumerate languages and the default.
	 *
	 * @var LanguageManager
	 */
	private LanguageManager $languages;

	/**
	 * Language data-access used to persist activation + default flags.
	 *
	 * @var Repository
	 */
	private Repository $language_repository;

	/**
	 * Construct the wizard.
	 *
	 * @param LanguageManager $languages           Language directory.
	 * @param Repository      $language_repository Language data-access.
	 */
	public function __construct( LanguageManager $languages, Repository $language_repository ) {
		$this->languages           = $languages;
		$this->language_repository = $language_repository;
	}

	/**
	 * Register hooks: detect fresh activation and route save / skip
	 * form submissions.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_wizard' ) );
		add_action( 'admin_post_openpoly_setup_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_openpoly_setup_skip', array( $this, 'handle_skip' ) );
	}

	/**
	 * Admin_init callback: redirect first-time admins to the wizard.
	 *
	 * @return void
	 */
	public function maybe_redirect_to_wizard(): void {
		if ( ! is_admin() ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( '' !== (string) get_option( self::SETTINGS_OPTION, '' ) ) {
			return; // already configured
		}
		if ( '1' === (string) get_option( self::SKIPPED_OPTION, '0' ) ) {
			return;
		}
		// Only redirect on a normal admin request, not on the wizard
		// page itself (which would loop).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin page check.
		$page = isset( $_GET['page'] ) ? (string) wp_unslash( $_GET['page'] ) : '';
		if ( 'openpoly-setup' === $page ) {
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=openpoly-setup' ), 302 );
		exit;
	}

	/**
	 * Render the wizard.
	 *
	 * @return void
	 */
	public function render(): void {
		$step     = isset( $_GET['step'] ) ? max( 1, min( 4, (int) $_GET['step'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only step indicator.
		$settings = $this->load_settings();
		?>
		<div class="wrap openpoly-setup-wizard">
			<h1><?php esc_html_e( 'OpenPoly Setup', 'openpoly' ); ?></h1>
			<ol class="openpoly-setup-steps">
				<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
					<li class="<?php echo $i === $step ? 'active' : ( $i < $step ? 'done' : '' ); ?>">
						<?php echo esc_html( $this->step_label( $i ) ); ?>
					</li>
				<?php endfor; ?>
			</ol>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="action" value="openpoly_setup_save" />
				<input type="hidden" name="step" value="<?php echo (int) $step; ?>" />
				<?php
				switch ( $step ) {
					case 2:
						$this->render_step_2();
						break;
					case 3:
						$this->render_step_3( $settings );
						break;
					case 4:
						$this->render_step_4( $settings );
						break;
					case 1:
					default:
						$this->render_step_1( $settings );
				}
				?>
				<p>
					<?php if ( $step > 1 ) : ?>
						<a class="button" href="<?php echo esc_url( add_query_arg( 'step', $step - 1 ) ); ?>"><?php esc_html_e( 'Back', 'openpoly' ); ?></a>
					<?php endif; ?>
					<button type="submit" class="button button-primary">
						<?php echo $step < 4 ? esc_html__( 'Next', 'openpoly' ) : esc_html__( 'Finish', 'openpoly' ); ?>
					</button>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin-post.php?action=openpoly_setup_skip&_wpnonce=' . wp_create_nonce( self::NONCE_ACTION ) ) ); ?>">
						<?php esc_html_e( 'Skip', 'openpoly' ); ?>
					</a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render step 1: confirm default language.
	 *
	 * @param array<string, mixed> $settings Persisted settings.
	 * @return void
	 */
	private function render_step_1( array $settings ): void {
		$default = isset( $settings['default_language'] ) ? (string) $settings['default_language'] : 'en_US';
		?>
		<h2><?php esc_html_e( 'Confirm default language', 'openpoly' ); ?></h2>
		<p>
			<label for="openpoly-default-language"><?php esc_html_e( 'Default language', 'openpoly' ); ?>:</label>
			<select name="default_language" id="openpoly-default-language">
				<?php foreach ( Catalog::all() as $entry ) : ?>
					<option value="<?php echo esc_attr( (string) $entry['code'] ); ?>" <?php selected( $default, (string) $entry['code'] ); ?>>
						<?php echo esc_html( (string) $entry['native_name'] ); ?> (<?php echo esc_html( (string) $entry['code'] ); ?>)
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Render step 2: pick target languages to activate.
	 *
	 * @return void
	 */
	private function render_step_2(): void {
		?>
		<h2><?php esc_html_e( 'Activate target languages', 'openpoly' ); ?></h2>
		<p><?php esc_html_e( 'Choose the languages you want to enable for visitors. You can change this later.', 'openpoly' ); ?></p>
		<ul class="openpoly-setup-languages">
			<?php foreach ( Catalog::all() as $entry ) : ?>
				<li>
					<label>
						<input type="checkbox" name="active_languages[]" value="<?php echo esc_attr( (string) $entry['code'] ); ?>" checked />
						<?php echo esc_html( (string) $entry['native_name'] ); ?> <code><?php echo esc_html( (string) $entry['code'] ); ?></code>
					</label>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Render step 3: choose URL structure.
	 *
	 * @param array<string, mixed> $settings Persisted settings.
	 * @return void
	 */
	private function render_step_3( array $settings ): void {
		$url_mode = isset( $settings['url_mode'] ) ? (string) $settings['url_mode'] : 'directory';
		?>
		<h2><?php esc_html_e( 'URL structure', 'openpoly' ); ?></h2>
		<p>
			<label><input type="radio" name="url_mode" value="directory" <?php checked( $url_mode, 'directory' ); ?> /> <?php esc_html_e( 'Directory (recommended): /en/hello/', 'openpoly' ); ?></label><br />
			<label><input type="radio" name="url_mode" value="parameter" <?php checked( $url_mode, 'parameter' ); ?> /> <?php esc_html_e( 'Parameter: /hello/?lang=en', 'openpoly' ); ?></label><br />
			<label><input type="radio" name="url_mode" value="domain" <?php checked( $url_mode, 'domain' ); ?> /> <?php esc_html_e( 'Subdomain: en.example.com (advanced)', 'openpoly' ); ?></label>
		</p>
		<?php
	}

	/**
	 * Render step 4: summary.
	 *
	 * @param array<string, mixed> $settings Persisted settings.
	 * @return void
	 */
	private function render_step_4( array $settings ): void {
		?>
		<h2><?php esc_html_e( 'Ready to finish', 'openpoly' ); ?></h2>
		<table class="widefat">
			<tr><th><?php esc_html_e( 'Default language', 'openpoly' ); ?></th><td><code><?php echo esc_html( isset( $settings['default_language'] ) ? (string) $settings['default_language'] : '' ); ?></code></td></tr>
			<tr><th><?php esc_html_e( 'Active languages', 'openpoly' ); ?></th><td><?php echo esc_html( implode( ', ', isset( $settings['active_languages'] ) ? (array) $settings['active_languages'] : array() ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'URL mode', 'openpoly' ); ?></th><td><code><?php echo esc_html( isset( $settings['url_mode'] ) ? (string) $settings['url_mode'] : '' ); ?></code></td></tr>
		</table>
		<?php
	}

	/**
	 * Return the human label for a wizard step.
	 *
	 * @param int $step Step number, 1..4.
	 * @return string
	 */
	private function step_label( int $step ): string {
		switch ( $step ) {
			case 1:
				return __( '1. Default language', 'openpoly' );
			case 2:
				return __( '2. Target languages', 'openpoly' );
			case 3:
				return __( '3. URL structure', 'openpoly' );
			case 4:
				return __( '4. Finish', 'openpoly' );
			default:
				return '';
		}
	}

	/**
	 * Handle the save POST. Validate, persist, advance to next step
	 * (or redirect to settings on the last step).
	 *
	 * @return void
	 */
	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'openpoly' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- direct call to wp_verify_nonce below.
		$nonce = isset( $_POST['_wpnonce'] ) ? (string) wp_unslash( $_POST['_wpnonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'openpoly' ) );
		}

		$step     = isset( $_POST['step'] ) ? max( 1, min( 4, (int) $_POST['step'] ) ) : 1;
		$settings = $this->load_settings();

		if ( 1 === $step && isset( $_POST['default_language'] ) ) {
			$settings['default_language'] = sanitize_text_field( (string) wp_unslash( $_POST['default_language'] ) );
		}
		if ( 2 === $step && isset( $_POST['active_languages'] ) && is_array( $_POST['active_languages'] ) ) {
			$codes = array_map(
				static function ( $code ): string {
					return sanitize_key( (string) wp_unslash( $code ) );
				},
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via array_map above.
								$_POST['active_languages']
			);
			$settings['active_languages'] = $codes;
		}
		if ( 3 === $step && isset( $_POST['url_mode'] ) ) {
			$mode = (string) wp_unslash( $_POST['url_mode'] );
			if ( in_array( $mode, array( 'directory', 'parameter', 'domain' ), true ) ) {
				$settings['url_mode'] = $mode;
			}
		}

		update_option( self::SETTINGS_OPTION, $settings );

		if ( $step < 4 ) {
			wp_safe_redirect( add_query_arg( 'step', $step + 1, admin_url( 'admin.php?page=openpoly-setup' ) ), 302 );
		} else {
			$this->apply_settings( $settings );
			wp_safe_redirect( admin_url( 'admin.php?page=openpoly&setup=complete' ), 302 );
		}
		exit;
	}

	/**
	 * Handle the skip POST. Mark the wizard as skipped and redirect
	 * to the dashboard.
	 *
	 * @return void
	 */
	public function handle_skip(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'openpoly' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- direct call below.
		$nonce = isset( $_GET['_wpnonce'] ) ? (string) wp_unslash( $_GET['_wpnonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'openpoly' ) );
		}
		update_option( self::SKIPPED_OPTION, '1' );
		wp_safe_redirect( admin_url(), 302 );
		exit;
	}

	/**
	 * Apply the wizard settings: mark the default language, activate
	 * the chosen languages, set the URL mode.
	 *
	 * @param array<string, mixed> $settings Persisted settings.
	 * @return void
	 */
	private function apply_settings( array $settings ): void {
		// 1. Mark default language.
		$default = isset( $settings['default_language'] ) ? (string) $settings['default_language'] : '';
		if ( '' !== $default ) {
			foreach ( $this->languages->active_languages() as $row ) {
				$is_default = ( (string) $row['code'] === $default );
				$this->language_repository->upsert(
					array(
						'code'         => (string) $row['code'],
						'english_name' => (string) $row['english_name'],
						'native_name'  => (string) $row['native_name'],
						'locale'       => (string) $row['locale'],
						'hreflang'     => (string) $row['hreflang'],
						'flag'         => (string) $row['flag'],
						'is_active'    => 1,
						'is_default'   => $is_default ? 1 : 0,
						'is_hidden'    => 0,
					)
				);
			}
		}

		// 2. Activate chosen languages, deactivate the rest.
		$chosen = isset( $settings['active_languages'] ) && is_array( $settings['active_languages'] )
			? array_flip( (array) $settings['active_languages'] )
			: array();
		foreach ( $this->languages->active_languages() as $row ) {
			$is_active = isset( $chosen[ (string) $row['code'] ] );
			$this->language_repository->set_active( (int) $row['id'], $is_active );
		}
	}

	/**
	 * Read the current persisted settings, with sensible defaults.
	 *
	 * @return array<string, mixed>
	 */
	private function load_settings(): array {
		$raw = get_option( self::SETTINGS_OPTION, array() );
		return is_array( $raw ) ? $raw : array();
	}
}
