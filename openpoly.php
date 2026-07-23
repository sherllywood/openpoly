<?php
/**
 * Plugin Name: OpenPoly
 * Plugin URI:  https://openpoly.example
 * Description: GPL multilingual WordPress plugin — WPML functional clone.
 * Version:     0.5.0-dev
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: openpoly
 * Domain Path: /languages
 *
 * @package OpenPoly
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'OPENPOLY_VERSION', '0.5.0-dev' );
define( 'OPENPOLY_FILE', __FILE__ );
define( 'OPENPOLY_DIR', plugin_dir_path( __FILE__ ) );
define( 'OPENPOLY_URL', plugin_dir_url( __FILE__ ) );

require_once OPENPOLY_DIR . 'vendor/autoload.php';

register_activation_hook(
	__FILE__,
	static function (): void {
		\OpenPoly\Bootstrap\Activator::on_activation();
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		\OpenPoly\Bootstrap\Activator::init();
	},
	1
);
