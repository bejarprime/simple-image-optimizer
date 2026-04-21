<?php
/**
 * AJAX controller.
 *
 * @package Simple_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles secure admin AJAX actions.
 */
class SIO_Ajax {
	/** AJAX nonce action. */
	const NONCE_ACTION = 'sio_admin_ajax';

	/** @var SIO_Options */
	private $options;

	/** @var SIO_Media_Scanner */
	private $scanner;

	/** @var SIO_Optimizer */
	private $optimizer;

	/**
	 * Constructor.
	 *
	 * @param SIO_Options       $options Options.
	 * @param SIO_Media_Scanner $scanner Scanner.
	 * @param SIO_Optimizer     $optimizer Optimizer.
	 */
	public function __construct( SIO_Options $options, SIO_Media_Scanner $scanner, SIO_Optimizer $optimizer ) {
		$this->options   = $options;
		$this->scanner   = $scanner;
		$this->optimizer = $optimizer;
	}

	/** Register hooks. */
	public function register_hooks() {
		add_action( 'wp_ajax_sio_scan_media', array( $this, 'scan_media' ) );
		add_action( 'wp_ajax_sio_optimize_batch', array( $this, 'optimize_batch' ) );
		add_action( 'wp_ajax_sio_restore_attachment', array( $this, 'restore_attachment' ) );
		add_action( 'wp_ajax_sio_reset_stats', array( $this, 'reset_stats' ) );
	}

	/** Scan media endpoint. */
	public function scan_media() {
		$this->verify_request();

		$page     = isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( wp_unslash( $_POST['per_page'] ) ) : 100;
		$scan     = $this->scanner->scan( $page, $per_page, false );

		wp_send_json_success( $scan );
	}

	/** Optimize batch endpoint. */
	public function optimize_batch() {
		$this->verify_request();

		$ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'absint', wp_unslash( $_POST['ids'] ) ) : array();
		$ids = array_values( array_filter( array_unique( $ids ) ) );

		$options = $this->options->get();
		$ids     = array_slice( $ids, 0, (int) $options['batch_size'] );
		$results = array();

		foreach ( $ids as $id ) {
			$result    = $this->optimizer->optimize_attachment( $id );
			$results[] = $result;
			$this->options->add_result_to_stats( $result );
			$this->options->record_recent_result( $result );
		}

		wp_send_json_success(
			array(
				'results' => $results,
				'stats'   => $this->options->get_stats(),
				'recent'  => $this->options->get_recent_results(),
			)
		);
	}

	/** Restore attachment endpoint. */
	public function restore_attachment() {
		$this->verify_request();

		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		if ( 0 === $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'simple-image-optimizer' ) ), 400 );
		}

		$result = $this->optimizer->restore_attachment( $id );
		$this->options->record_recent_result( $result );

		if ( 'restored' !== $result['status'] ) {
			wp_send_json_error( array( 'message' => $result['message'], 'result' => $result ), 400 );
		}

		wp_send_json_success( array( 'result' => $result ) );
	}

	/** Reset stats endpoint. */
	public function reset_stats() {
		$this->verify_request();
		update_option( SIO_Options::STATS_OPTION_NAME, SIO_Options::default_stats(), false );
		update_option( SIO_Options::RECENT_RESULTS_OPTION_NAME, array(), false );
		wp_send_json_success( array( 'stats' => SIO_Options::default_stats() ) );
	}

	/** Verify capability and nonce. */
	private function verify_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'simple-image-optimizer' ) ), 403 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'simple-image-optimizer' ) ), 403 );
		}
	}
}
