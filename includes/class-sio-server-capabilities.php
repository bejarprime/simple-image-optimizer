<?php
/**
 * Server capability detection.
 *
 * @package Simple_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects local image processing capabilities.
 */
class SIO_Server_Capabilities {
	/**
	 * Get capability summary.
	 *
	 * @return array
	 */
	public function get() {
		$uploads = wp_get_upload_dir();

		return array(
			'gd'                => $this->has_gd(),
			'imagick'           => $this->has_imagick(),
			'webp'              => $this->supports_webp(),
			'uploads_writable'  => $this->uploads_writable( $uploads ),
			'uploads_basedir'   => isset( $uploads['basedir'] ) ? $uploads['basedir'] : '',
			'preferred_editor'  => $this->preferred_editor_label(),
			'can_process_local' => $this->has_gd() || $this->has_imagick(),
		);
	}

	/** @return bool */
	private function has_gd() {
		return extension_loaded( 'gd' ) && function_exists( 'gd_info' );
	}

	/** @return bool */
	private function has_imagick() {
		return extension_loaded( 'imagick' ) && class_exists( 'Imagick' );
	}

	/** @return bool */
	private function supports_webp() {
		if ( function_exists( 'imagewebp' ) ) {
			return true;
		}

		if ( class_exists( 'Imagick' ) ) {
			try {
				return in_array( 'WEBP', Imagick::queryFormats( 'WEBP' ), true );
			} catch ( Exception $exception ) {
				return false;
			}
		}

		return false;
	}

	/**
	 * Check uploads writability.
	 *
	 * @param array $uploads Upload dir info.
	 * @return bool
	 */
	private function uploads_writable( array $uploads ) {
		return ! empty( $uploads['basedir'] ) && is_dir( $uploads['basedir'] ) && wp_is_writable( $uploads['basedir'] );
	}

	/**
	 * Preferred editor label.
	 *
	 * @return string
	 */
	private function preferred_editor_label() {
		if ( $this->has_imagick() ) {
			return 'Imagick';
		}

		if ( $this->has_gd() ) {
			return 'GD';
		}

		return __( 'Not available', 'simple-image-optimizer' );
	}
}

