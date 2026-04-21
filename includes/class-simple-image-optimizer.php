<?php
/**
 * Main plugin class.
 *
 * @package Simple_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin bootstrap.
 */
class Simple_Image_Optimizer {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Options service.
	 *
	 * @var SIO_Options
	 */
	private $options;

	/**
	 * Return singleton instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->options = new SIO_Options();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'ensure_options' ), 5 );

		$admin = new SIO_Admin( $this->options, new SIO_Server_Capabilities() );
		$admin->register_hooks();

		$ajax = new SIO_Ajax(
			$this->options,
			new SIO_Media_Scanner(),
			new SIO_Optimizer( $this->options )
		);
		$ajax->register_hooks();
	}

	/**
	 * Ensure normalized options exist.
	 *
	 * @return void
	 */
	public function ensure_options() {
		SIO_Options::ensure_defaults();
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'simple-image-optimizer', false, dirname( plugin_basename( SIO_FILE ) ) . '/languages' );
	}
}
