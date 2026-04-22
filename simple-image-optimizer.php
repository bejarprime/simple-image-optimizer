<?php
/**
 * Plugin Name: Simple Image Optimizer
 * Plugin URI: https://github.com/bejarprime/simple-image-optimizer
 * Description: Lightweight local image optimization for WordPress media libraries, with batch processing and optional WebP generation.
 * Version: 0.1.3
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: WPHubb
 * Author URI: https://wphubb.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-image-optimizer
 * Domain Path: /languages
 *
 * @package Simple_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIO_VERSION', '0.1.3' );
define( 'SIO_FILE', __FILE__ );
define( 'SIO_PATH', plugin_dir_path( __FILE__ ) );
define( 'SIO_URL', plugin_dir_url( __FILE__ ) );

require_once SIO_PATH . 'includes/class-sio-options.php';
require_once SIO_PATH . 'includes/class-sio-server-capabilities.php';
require_once SIO_PATH . 'includes/class-sio-media-scanner.php';
require_once SIO_PATH . 'includes/class-sio-optimizer.php';
require_once SIO_PATH . 'includes/class-sio-frontend.php';
require_once SIO_PATH . 'includes/class-sio-ajax.php';
require_once SIO_PATH . 'includes/class-sio-admin.php';
require_once SIO_PATH . 'includes/class-simple-image-optimizer.php';

/**
 * Bootstrap the plugin.
 *
 * @return void
 */
function sio_bootstrap() {
	Simple_Image_Optimizer::instance();
}
add_action( 'plugins_loaded', 'sio_bootstrap' );

/**
 * Ensure default options on activation.
 *
 * @return void
 */
function sio_activate() {
	SIO_Options::ensure_defaults();
}
register_activation_hook( __FILE__, 'sio_activate' );
