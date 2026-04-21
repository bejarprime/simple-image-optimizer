<?php
/**
 * Image optimizer.
 *
 * @package Simple_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optimizes attachment files locally.
 */
class SIO_Optimizer {
	/**
	 * Options service.
	 *
	 * @var SIO_Options
	 */
	private $options;

	/**
	 * Constructor.
	 *
	 * @param SIO_Options $options Options.
	 */
	public function __construct( SIO_Options $options ) {
		$this->options = $options;
	}

	/**
	 * Optimize an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array
	 */
	public function optimize_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		$options       = $this->options->get();
		$result        = array(
			'id'           => $attachment_id,
			'title'        => get_the_title( $attachment_id ),
			'filename'     => '',
			'status'       => 'error',
			'optimized'    => false,
			'skipped'      => false,
			'message'      => '',
			'bytes_before' => 0,
			'bytes_after'  => 0,
			'bytes_saved'  => 0,
			'webp_path'    => '',
			'webp_created' => false,
			'backup_path'  => '',
			'backup_created' => false,
			'time'         => current_time( 'mysql' ),
		);

		if ( '1' === (string) get_post_meta( $attachment_id, '_sio_optimized', true ) ) {
			$result['skipped'] = true;
			$result['status']  = 'skipped';
			$result['message'] = __( 'Already optimized.', 'simple-image-optimizer' );
			return $result;
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime, SIO_Media_Scanner::SUPPORTED_MIME_TYPES, true ) ) {
			$result['skipped'] = true;
			$result['status']  = 'skipped';
			$result['message'] = __( 'Unsupported image type.', 'simple-image-optimizer' );
			return $result;
		}

		$path = get_attached_file( $attachment_id );
		if ( ! $this->is_safe_upload_path( $path ) || ! file_exists( $path ) ) {
			$result['message'] = __( 'Image file not found or outside uploads directory.', 'simple-image-optimizer' );
			$this->save_error( $attachment_id, $result['message'] );
			return $result;
		}

		$result['filename'] = wp_basename( $path );

		$bytes_before = filesize( $path );
		$result['bytes_before'] = false === $bytes_before ? 0 : (int) $bytes_before;

		if ( ! function_exists( 'wp_get_image_editor' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		if ( ! empty( $options['keep_originals'] ) ) {
			$backup_path = $this->create_backup( $path );
			if ( '' !== $backup_path ) {
				$result['backup_path']    = $backup_path;
				$result['backup_created'] = true;
				update_post_meta( $attachment_id, '_sio_backup_path', $backup_path );
			}
		}

		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			$result['message'] = $editor->get_error_message();
			$this->save_error( $attachment_id, $result['message'] );
			return $result;
		}

		if ( method_exists( $editor, 'set_quality' ) ) {
			$editor->set_quality( (int) $options['jpeg_quality'] );
		}

		$size = $editor->get_size();
		if ( ! is_wp_error( $size ) && is_array( $size ) ) {
			$should_resize = $this->should_resize( $size, $options );
			if ( $should_resize ) {
				$editor->resize( (int) $options['max_width'], (int) $options['max_height'], false );
			}
		}

		$saved = $editor->save( $path, $mime );
		if ( is_wp_error( $saved ) ) {
			$result['message'] = $saved->get_error_message();
			$this->save_error( $attachment_id, $result['message'] );
			return $result;
		}

		clearstatcache( true, $path );
		$bytes_after = filesize( $path );
		$result['bytes_after'] = false === $bytes_after ? $result['bytes_before'] : (int) $bytes_after;
		$result['bytes_saved'] = max( 0, $result['bytes_before'] - $result['bytes_after'] );

		if ( ! empty( $options['generate_webp'] ) ) {
			$webp = $this->generate_webp( $path, (int) $options['webp_quality'] );
			if ( ! is_wp_error( $webp ) && '' !== $webp ) {
				$result['webp_path']    = $webp;
				$result['webp_created'] = true;
				update_post_meta( $attachment_id, '_sio_webp_path', $webp );
			}
		}

		update_post_meta( $attachment_id, '_sio_optimized', '1' );
		update_post_meta( $attachment_id, '_sio_original_size', $result['bytes_before'] );
		update_post_meta( $attachment_id, '_sio_optimized_size', $result['bytes_after'] );
		delete_post_meta( $attachment_id, '_sio_last_error' );

		$result['optimized'] = true;
		$result['status']    = 'optimized';
		$result['message']   = __( 'Optimized successfully.', 'simple-image-optimizer' );

		return $result;
	}

	/**
	 * Check safe upload path.
	 *
	 * @param string $path File path.
	 * @return bool
	 */
	private function is_safe_upload_path( $path ) {
		if ( ! is_string( $path ) || '' === $path ) {
			return false;
		}

		$uploads = wp_get_upload_dir();
		$base    = ! empty( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false;
		$real    = realpath( $path );

		return $base && $real && 0 === strpos( wp_normalize_path( $real ), wp_normalize_path( $base ) );
	}

	/**
	 * Create backup next to the original file.
	 *
	 * @param string $path File path.
	 * @return string
	 */
	private function create_backup( $path ) {
		$info   = pathinfo( $path );
		$backup = trailingslashit( $info['dirname'] ) . $info['filename'] . '.sio-original.' . $info['extension'];

		if ( ! file_exists( $backup ) && wp_is_writable( $info['dirname'] ) ) {
			@copy( $path, $backup ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_copy
		}

		return file_exists( $backup ) ? $backup : '';
	}

	/**
	 * Should resize image.
	 *
	 * @param array $size Editor size.
	 * @param array $options Options.
	 * @return bool
	 */
	private function should_resize( array $size, array $options ) {
		$width      = isset( $size['width'] ) ? absint( $size['width'] ) : 0;
		$height     = isset( $size['height'] ) ? absint( $size['height'] ) : 0;
		$max_width  = absint( $options['max_width'] );
		$max_height = absint( $options['max_height'] );

		return ( $max_width > 0 && $width > $max_width ) || ( $max_height > 0 && $height > $max_height );
	}

	/**
	 * Generate WebP sibling file.
	 *
	 * @param string $path Original path.
	 * @param int    $quality WebP quality.
	 * @return string|WP_Error
	 */
	private function generate_webp( $path, $quality ) {
		$webp_path = preg_replace( '/\.[^.]+$/', '.webp', $path );
		if ( ! is_string( $webp_path ) || '' === $webp_path ) {
			return new WP_Error( 'sio_webp_path', __( 'Could not build WebP path.', 'simple-image-optimizer' ) );
		}

		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			return $editor;
		}

		if ( method_exists( $editor, 'set_quality' ) ) {
			$editor->set_quality( $quality );
		}

		$saved = $editor->save( $webp_path, 'image/webp' );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return $webp_path;
	}

	/**
	 * Save last error for attachment.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $message Error.
	 * @return void
	 */
	private function save_error( $attachment_id, $message ) {
		update_post_meta( $attachment_id, '_sio_last_error', sanitize_text_field( $message ) );
	}
}
