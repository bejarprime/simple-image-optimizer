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
		$result        = $this->get_default_result( $attachment_id );

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

		$this->load_image_editor();

		$result['filename'] = wp_basename( $path );
		$files              = $this->get_attachment_files( $attachment_id, $path, $mime, ! empty( $options['optimize_sizes'] ) );
		$webp_files         = array();
		$backup_files       = array();
		$kept_originals     = 0;

		foreach ( $files as $file ) {
			$file_result = $this->optimize_file( $file, $options );

			if ( is_wp_error( $file_result ) ) {
				$result['message'] = $file_result->get_error_message();
				$this->save_error( $attachment_id, $result['message'] );
				return $result;
			}

			$result['bytes_before'] += $file_result['bytes_before'];
			$result['bytes_after']  += $file_result['bytes_after'];
			$result['bytes_saved']   = max( 0, $result['bytes_before'] - $result['bytes_after'] );

			if ( ! empty( $file_result['webp_path'] ) ) {
				$webp_files[] = $file_result['webp_path'];
			}

			if ( ! empty( $file_result['backup_path'] ) ) {
				$backup_files[] = $file_result['backup_path'];
			}

			if ( ! empty( $file_result['kept_original'] ) ) {
				$kept_originals++;
			}

			if ( empty( $file['is_main'] ) ) {
				$result['sizes_processed']++;
			}
		}

		if ( ! empty( $webp_files ) ) {
			$result['webp_path']    = $webp_files[0];
			$result['webp_created'] = true;
			update_post_meta( $attachment_id, '_sio_webp_path', $webp_files[0] );
			update_post_meta( $attachment_id, '_sio_webp_files', array_values( array_unique( $webp_files ) ) );
		}

		if ( ! empty( $backup_files ) ) {
			$result['backup_path']    = $backup_files[0];
			$result['backup_created'] = true;
			update_post_meta( $attachment_id, '_sio_backup_path', $backup_files[0] );
			update_post_meta( $attachment_id, '_sio_backup_files', array_values( array_unique( $backup_files ) ) );
		}

		update_post_meta( $attachment_id, '_sio_optimized', '1' );
		update_post_meta( $attachment_id, '_sio_original_size', $result['bytes_before'] );
		update_post_meta( $attachment_id, '_sio_optimized_size', $result['bytes_after'] );
		update_post_meta( $attachment_id, '_sio_sizes_processed', $result['sizes_processed'] );
		$this->refresh_main_metadata_dimensions( $attachment_id, $path );
		delete_post_meta( $attachment_id, '_sio_last_error' );

		$result['optimized'] = true;
		$result['status']    = 'optimized';
		$result['message']   = $kept_originals > 0
			? __( 'Optimized safely. Files that became larger were kept unchanged.', 'simple-image-optimizer' )
			: __( 'Optimized successfully.', 'simple-image-optimizer' );
		$result['kept_originals'] = $kept_originals;

		return $result;
	}

	/**
	 * Restore original files from local backups.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array
	 */
	public function restore_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		$result        = $this->get_default_result( $attachment_id );
		$main_path     = get_attached_file( $attachment_id );

		$result['status']   = 'error';
		$result['filename'] = $main_path ? wp_basename( $main_path ) : '';

		if ( ! $this->is_safe_upload_path( $main_path ) ) {
			$result['message'] = __( 'Original image path is not safe.', 'simple-image-optimizer' );
			return $result;
		}

		$backup_files = get_post_meta( $attachment_id, '_sio_backup_files', true );
		$backup_files = is_array( $backup_files ) ? $backup_files : array();
		$main_backup  = (string) get_post_meta( $attachment_id, '_sio_backup_path', true );
		$backup_files = array_values( array_unique( array_filter( array_merge( array( $main_backup ), $backup_files ) ) ) );
		$restored     = 0;
		$bytes_before = 0;
		$bytes_after  = 0;

		foreach ( $backup_files as $backup_path ) {
			if ( ! $this->is_safe_upload_path( $backup_path ) || ! file_exists( $backup_path ) ) {
				continue;
			}

			$target_path = $this->target_from_backup_path( $backup_path );
			if ( '' === $target_path || $target_path === $backup_path || ! $this->is_safe_upload_path( $target_path ) || ! wp_is_writable( dirname( $target_path ) ) ) {
				continue;
			}

			$target_size  = file_exists( $target_path ) ? filesize( $target_path ) : 0;
			$backup_size  = filesize( $backup_path );
			$bytes_before += false === $target_size ? 0 : (int) $target_size;
			$bytes_after  += false === $backup_size ? 0 : (int) $backup_size;

			if ( @copy( $backup_path, $target_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_copy
				$restored++;
			}
		}

		if ( 0 === $restored ) {
			$result['message'] = __( 'No readable backup file was found for this image.', 'simple-image-optimizer' );
			return $result;
		}

		$this->delete_webp_files( $attachment_id );
		$this->refresh_main_metadata_dimensions( $attachment_id, $main_path );
		$this->clear_optimization_meta( $attachment_id );

		$result['optimized']    = false;
		$result['status']       = 'restored';
		$result['bytes_before'] = $bytes_before;
		$result['bytes_after']  = $bytes_after;
		$result['bytes_saved']  = 0;
		$result['message']      = sprintf(
			/* translators: %d: number of restored files. */
			_n( '%d file restored from backup.', '%d files restored from backup.', $restored, 'simple-image-optimizer' ),
			$restored
		);

		return $result;
	}

	/**
	 * Build a default result array.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array
	 */
	private function get_default_result( $attachment_id ) {
		return array(
			'id'              => absint( $attachment_id ),
			'title'           => get_the_title( $attachment_id ),
			'filename'        => '',
			'status'          => 'error',
			'optimized'       => false,
			'skipped'         => false,
			'message'         => '',
			'bytes_before'    => 0,
			'bytes_after'     => 0,
			'bytes_saved'     => 0,
			'webp_path'       => '',
			'webp_created'    => false,
			'backup_path'     => '',
			'backup_created'  => false,
			'sizes_processed' => 0,
			'kept_originals'  => 0,
			'time'            => current_time( 'mysql' ),
		);
	}

	/** Load WordPress image editor functions when needed. */
	private function load_image_editor() {
		if ( ! function_exists( 'wp_get_image_editor' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
	}

	/**
	 * Get the main attachment file plus generated sizes.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $main_path Main file path.
	 * @param string $mime MIME type.
	 * @param bool   $include_sizes Include generated sizes.
	 * @return array
	 */
	private function get_attachment_files( $attachment_id, $main_path, $mime, $include_sizes ) {
		$files = array(
			array(
				'path'         => $main_path,
				'mime'         => $mime,
				'is_main'      => true,
				'allow_resize' => true,
			),
		);

		if ( ! $include_sizes ) {
			return $files;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
			return $files;
		}

		$base_dir = dirname( $main_path );
		foreach ( $metadata['sizes'] as $size ) {
			if ( empty( $size['file'] ) || ! is_string( $size['file'] ) ) {
				continue;
			}

			$size_path = trailingslashit( $base_dir ) . wp_basename( $size['file'] );
			if ( $size_path === $main_path || ! $this->is_safe_upload_path( $size_path ) || ! file_exists( $size_path ) ) {
				continue;
			}

			$files[] = array(
				'path'         => $size_path,
				'mime'         => ! empty( $size['mime-type'] ) ? $size['mime-type'] : $mime,
				'is_main'      => false,
				'allow_resize' => false,
			);
		}

		return $files;
	}

	/**
	 * Optimize one physical file.
	 *
	 * @param array $file File data.
	 * @param array $options Options.
	 * @return array|WP_Error
	 */
	private function optimize_file( array $file, array $options ) {
		$path = $file['path'];
		$mime = $file['mime'];

		if ( ! $this->is_safe_upload_path( $path ) || ! file_exists( $path ) ) {
			return new WP_Error( 'sio_missing_file', __( 'Image file not found or outside uploads directory.', 'simple-image-optimizer' ) );
		}

		$bytes_before = filesize( $path );
		$bytes_before = false === $bytes_before ? 0 : (int) $bytes_before;
		$backup_path  = '';
		$webp_path    = '';

		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$temp_path    = wp_tempnam( wp_basename( $path ) );
		$kept_original = false;

		if ( ! $temp_path || ! @copy( $path, $temp_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_copy
			return new WP_Error( 'sio_temp_copy_failed', __( 'Could not create a temporary safety copy before optimization.', 'simple-image-optimizer' ) );
		}

		if ( ! empty( $options['keep_originals'] ) ) {
			$backup_path = $this->create_backup( $path );
		}

		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			wp_delete_file( $temp_path );
			return $editor;
		}

		if ( method_exists( $editor, 'set_quality' ) ) {
			$editor->set_quality( (int) $options['jpeg_quality'] );
		}

		$size = $editor->get_size();
		if ( ! empty( $file['allow_resize'] ) && ! is_wp_error( $size ) && is_array( $size ) && $this->should_resize( $size, $options ) ) {
			$editor->resize( (int) $options['max_width'], (int) $options['max_height'], false );
		}

		$saved = $editor->save( $path, $mime );
		if ( is_wp_error( $saved ) ) {
			wp_delete_file( $temp_path );
			return $saved;
		}

		clearstatcache( true, $path );
		$bytes_after = filesize( $path );
		$bytes_after = false === $bytes_after ? $bytes_before : (int) $bytes_after;

		if ( $bytes_after > $bytes_before ) {
			@copy( $temp_path, $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_copy
			clearstatcache( true, $path );
			$restored_size = filesize( $path );
			$bytes_after   = false === $restored_size ? $bytes_before : (int) $restored_size;
			$kept_original = true;
		}

		wp_delete_file( $temp_path );

		if ( ! empty( $options['generate_webp'] ) ) {
			$webp = $this->generate_webp( $path, (int) $options['webp_quality'] );
			if ( ! is_wp_error( $webp ) && '' !== $webp ) {
				$webp_path = $webp;
			}
		}

		return array(
			'bytes_before' => $bytes_before,
			'bytes_after'  => $bytes_after,
			'bytes_saved'  => max( 0, $bytes_before - $bytes_after ),
			'backup_path'  => $backup_path,
			'webp_path'    => $webp_path,
			'kept_original' => $kept_original,
		);
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

		if ( ! $base || ! $real ) {
			return false;
		}

		$base = untrailingslashit( wp_normalize_path( $base ) );
		$real = wp_normalize_path( $real );

		return $real === $base || 0 === strpos( $real, trailingslashit( $base ) );
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
	 * Get target path for a SIO backup.
	 *
	 * @param string $backup_path Backup path.
	 * @return string
	 */
	private function target_from_backup_path( $backup_path ) {
		if ( ! is_string( $backup_path ) || ! preg_match( '/\.sio-original\.[^.]+$/', $backup_path ) ) {
			return '';
		}

		$target_path = preg_replace( '/\.sio-original\.([^.]+)$/', '.$1', $backup_path );
		return is_string( $target_path ) ? $target_path : '';
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
	 * Remove generated WebP files for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private function delete_webp_files( $attachment_id ) {
		$webp_files = get_post_meta( $attachment_id, '_sio_webp_files', true );
		$webp_files = is_array( $webp_files ) ? $webp_files : array();
		$main_webp  = (string) get_post_meta( $attachment_id, '_sio_webp_path', true );
		$webp_files = array_values( array_unique( array_filter( array_merge( array( $main_webp ), $webp_files ) ) ) );

		foreach ( $webp_files as $webp_path ) {
			if ( $this->is_safe_upload_path( $webp_path ) && file_exists( $webp_path ) ) {
				wp_delete_file( $webp_path );
			}
		}
	}

	/**
	 * Refresh attachment metadata dimensions if the main image was resized.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $path Main file path.
	 * @return void
	 */
	private function refresh_main_metadata_dimensions( $attachment_id, $path ) {
		if ( ! function_exists( 'wp_getimagesize' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$image_size = wp_getimagesize( $path );
		if ( empty( $image_size[0] ) || empty( $image_size[1] ) ) {
			return;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $metadata ) ) {
			return;
		}

		$metadata['width']  = absint( $image_size[0] );
		$metadata['height'] = absint( $image_size[1] );

		update_post_meta( $attachment_id, '_wp_attachment_metadata', $metadata );
	}

	/**
	 * Clear optimization post meta.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private function clear_optimization_meta( $attachment_id ) {
		$keys = array(
			'_sio_optimized',
			'_sio_original_size',
			'_sio_optimized_size',
			'_sio_webp_path',
			'_sio_webp_files',
			'_sio_backup_path',
			'_sio_backup_files',
			'_sio_sizes_processed',
			'_sio_last_error',
		);

		foreach ( $keys as $key ) {
			delete_post_meta( $attachment_id, $key );
		}
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
