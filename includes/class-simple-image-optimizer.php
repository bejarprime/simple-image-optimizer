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
	 * Optimizer service.
	 *
	 * @var SIO_Optimizer
	 */
	private $optimizer;

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
		$this->options   = new SIO_Options();
		$this->optimizer = new SIO_Optimizer( $this->options );

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'ensure_options' ), 5 );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'maybe_optimize_new_upload' ), 20, 2 );

		$admin = new SIO_Admin( $this->options, new SIO_Server_Capabilities() );
		$admin->register_hooks();

		$frontend = new SIO_Frontend( $this->options );
		$frontend->register_hooks();

		$ajax = new SIO_Ajax(
			$this->options,
			new SIO_Media_Scanner(),
			$this->optimizer
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

	/**
	 * Automatically optimize new uploads after WordPress creates metadata.
	 *
	 * @param array $metadata Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array
	 */
	public function maybe_optimize_new_upload( $metadata, $attachment_id ) {
		$options = $this->options->get();
		if ( empty( $options['auto_optimize'] ) ) {
			return $metadata;
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime, SIO_Media_Scanner::SUPPORTED_MIME_TYPES, true ) ) {
			return $metadata;
		}

		if ( is_array( $metadata ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_metadata', $metadata );
		}

		$result = $this->optimizer->optimize_attachment( $attachment_id );
		$this->options->add_result_to_stats( $result );
		$this->options->record_recent_result( $result );

		$updated_metadata = wp_get_attachment_metadata( $attachment_id );
		return is_array( $updated_metadata ) ? $updated_metadata : $metadata;
	}
}
