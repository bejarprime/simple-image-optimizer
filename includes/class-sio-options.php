<?php
/**
 * Options service.
 *
 * @package Simple_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin options and defaults.
 */
class SIO_Options {
	/** Main option name. */
	const OPTION_NAME = 'simple_image_optimizer_options';

	/** Stats option name. */
	const STATS_OPTION_NAME = 'simple_image_optimizer_stats';

	/** Recent results option name. */
	const RECENT_RESULTS_OPTION_NAME = 'simple_image_optimizer_recent_results';

	/** Allowed quality presets. */
	const ALLOWED_QUALITY_PRESETS = array( 'high', 'balanced', 'compressed' );

	/**
	 * Default options.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'quality_preset'      => 'high',
			'jpeg_quality'        => 82,
			'webp_quality'        => 82,
			'max_width'           => 1920,
			'max_height'          => 1920,
			'batch_size'          => 3,
			'keep_originals'      => true,
			'generate_webp'       => true,
			'delete_on_uninstall' => false,
		);
	}

	/**
	 * Default stats.
	 *
	 * @return array
	 */
	public static function default_stats() {
		return array(
			'processed'    => 0,
			'skipped'      => 0,
			'errors'       => 0,
			'bytes_before' => 0,
			'bytes_after'  => 0,
			'last_run'     => '',
		);
	}

	/** Ensure defaults exist. */
	public static function ensure_defaults() {
		$existing = get_option( self::OPTION_NAME, null );

		if ( null === $existing ) {
			add_option( self::OPTION_NAME, self::defaults(), '', false );
		} else {
			update_option( self::OPTION_NAME, self::normalize( $existing ), false );
		}

		if ( null === get_option( self::STATS_OPTION_NAME, null ) ) {
			add_option( self::STATS_OPTION_NAME, self::default_stats(), '', false );
		}

		if ( null === get_option( self::RECENT_RESULTS_OPTION_NAME, null ) ) {
			add_option( self::RECENT_RESULTS_OPTION_NAME, array(), '', false );
		}
	}

	/**
	 * Get normalized options.
	 *
	 * @return array
	 */
	public function get() {
		return self::normalize( get_option( self::OPTION_NAME, array() ) );
	}

	/**
	 * Update options.
	 *
	 * @param array $options Options.
	 * @return bool
	 */
	public function update( array $options ) {
		return update_option( self::OPTION_NAME, self::normalize( $options ), false );
	}

	/**
	 * Get stats.
	 *
	 * @return array
	 */
	public function get_stats() {
		$stats = get_option( self::STATS_OPTION_NAME, array() );
		$stats = is_array( $stats ) ? wp_parse_args( $stats, self::default_stats() ) : self::default_stats();

		$stats['processed']    = absint( $stats['processed'] );
		$stats['skipped']      = absint( $stats['skipped'] );
		$stats['errors']       = absint( $stats['errors'] );
		$stats['bytes_before'] = max( 0, (int) $stats['bytes_before'] );
		$stats['bytes_after']  = max( 0, (int) $stats['bytes_after'] );
		$stats['last_run']     = is_string( $stats['last_run'] ) ? sanitize_text_field( $stats['last_run'] ) : '';

		return $stats;
	}

	/**
	 * Add a single optimization result to global stats.
	 *
	 * @param array $result Optimization result.
	 * @return void
	 */
	public function add_result_to_stats( array $result ) {
		$stats = $this->get_stats();

		if ( ! empty( $result['optimized'] ) ) {
			$stats['processed']++;
		} elseif ( ! empty( $result['skipped'] ) ) {
			$stats['skipped']++;
		} else {
			$stats['errors']++;
		}

		$stats['bytes_before'] += isset( $result['bytes_before'] ) ? max( 0, (int) $result['bytes_before'] ) : 0;
		$stats['bytes_after']  += isset( $result['bytes_after'] ) ? max( 0, (int) $result['bytes_after'] ) : 0;
		$stats['last_run']      = current_time( 'mysql' );

		update_option( self::STATS_OPTION_NAME, $stats, false );
	}

	/**
	 * Store a recent result for admin visibility.
	 *
	 * @param array $result Optimization result.
	 * @return void
	 */
	public function record_recent_result( array $result ) {
		$recent   = $this->get_recent_results();
		$recent[] = $this->normalize_recent_result( $result );
		$recent   = array_slice( array_reverse( $recent ), 0, 10 );
		$recent   = array_reverse( $recent );

		update_option( self::RECENT_RESULTS_OPTION_NAME, $recent, false );
	}

	/**
	 * Get recent optimization results.
	 *
	 * @return array
	 */
	public function get_recent_results() {
		$recent = get_option( self::RECENT_RESULTS_OPTION_NAME, array() );
		$recent = is_array( $recent ) ? $recent : array();
		$recent = array_values( array_map( array( $this, 'normalize_recent_result' ), $recent ) );

		if ( empty( $recent ) ) {
			$recent = $this->get_recent_results_from_meta();
		}

		return $recent;
	}

	/**
	 * Build recent results from attachment meta as a fallback for existing optimized images.
	 *
	 * @return array
	 */
	private function get_recent_results_from_meta() {
		$query = new WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_mime_type'         => array( 'image/jpeg', 'image/png' ),
				'fields'                 => 'ids',
				'posts_per_page'         => 10,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_sio_optimized',
						'value' => '1',
					),
				),
			)
		);

		$results = array();

		foreach ( $query->posts as $attachment_id ) {
			$bytes_before = (int) get_post_meta( $attachment_id, '_sio_original_size', true );
			$bytes_after  = (int) get_post_meta( $attachment_id, '_sio_optimized_size', true );
			$webp_path    = (string) get_post_meta( $attachment_id, '_sio_webp_path', true );
			$backup_path  = (string) get_post_meta( $attachment_id, '_sio_backup_path', true );

			$results[] = $this->normalize_recent_result(
				array(
					'id'             => $attachment_id,
					'title'          => get_the_title( $attachment_id ),
					'filename'       => wp_basename( get_attached_file( $attachment_id ) ),
					'status'         => 'optimized',
					'message'        => __( 'Optimized successfully.', 'simple-image-optimizer' ),
					'bytes_before'   => $bytes_before,
					'bytes_after'    => $bytes_after,
					'bytes_saved'    => max( 0, $bytes_before - $bytes_after ),
					'webp_created'   => '' !== $webp_path,
					'backup_created' => '' !== $backup_path,
					'time'           => get_post_modified_time( 'Y-m-d H:i:s', false, $attachment_id ),
				)
			);
		}

		return $results;
	}

	/**
	 * Normalize a recent result row.
	 *
	 * @param mixed $result Raw result.
	 * @return array
	 */
	private function normalize_recent_result( $result ) {
		$result = is_array( $result ) ? $result : array();

		return array(
			'id'             => isset( $result['id'] ) ? absint( $result['id'] ) : 0,
			'title'          => isset( $result['title'] ) ? sanitize_text_field( $result['title'] ) : '',
			'filename'       => isset( $result['filename'] ) ? sanitize_file_name( $result['filename'] ) : '',
			'status'         => isset( $result['status'] ) ? sanitize_key( $result['status'] ) : 'error',
			'message'        => isset( $result['message'] ) ? sanitize_text_field( $result['message'] ) : '',
			'bytes_before'   => isset( $result['bytes_before'] ) ? max( 0, (int) $result['bytes_before'] ) : 0,
			'bytes_after'    => isset( $result['bytes_after'] ) ? max( 0, (int) $result['bytes_after'] ) : 0,
			'bytes_saved'    => isset( $result['bytes_saved'] ) ? max( 0, (int) $result['bytes_saved'] ) : 0,
			'webp_created'   => ! empty( $result['webp_created'] ),
			'backup_created' => ! empty( $result['backup_created'] ),
			'time'           => isset( $result['time'] ) ? sanitize_text_field( $result['time'] ) : '',
		);
	}

	/**
	 * Normalize options.
	 *
	 * @param mixed $options Raw options.
	 * @return array
	 */
	public static function normalize( $options ) {
		$options  = is_array( $options ) ? $options : array();
		$merged   = wp_parse_args( $options, self::defaults() );
		$preset   = is_string( $merged['quality_preset'] ) ? sanitize_key( $merged['quality_preset'] ) : 'high';
		$preset   = in_array( $preset, self::ALLOWED_QUALITY_PRESETS, true ) ? $preset : 'high';
		$qualities = self::qualities_for_preset( $preset );

		$merged['quality_preset']      = $preset;
		$merged['jpeg_quality']        = self::normalize_quality( $merged['jpeg_quality'], $qualities['jpeg'] );
		$merged['webp_quality']        = self::normalize_quality( $merged['webp_quality'], $qualities['webp'] );
		$merged['max_width']           = self::normalize_dimension( $merged['max_width'], 1920 );
		$merged['max_height']          = self::normalize_dimension( $merged['max_height'], 1920 );
		$merged['batch_size']          = min( 10, max( 1, absint( $merged['batch_size'] ) ) );
		$merged['keep_originals']      = ! empty( $merged['keep_originals'] );
		$merged['generate_webp']       = ! empty( $merged['generate_webp'] );
		$merged['delete_on_uninstall'] = ! empty( $merged['delete_on_uninstall'] );

		return $merged;
	}

	/**
	 * Get qualities for preset.
	 *
	 * @param string $preset Preset key.
	 * @return array{jpeg:int,webp:int}
	 */
	public static function qualities_for_preset( $preset ) {
		switch ( $preset ) {
			case 'compressed':
				return array( 'jpeg' => 72, 'webp' => 74 );
			case 'balanced':
				return array( 'jpeg' => 78, 'webp' => 80 );
			case 'high':
			default:
				return array( 'jpeg' => 82, 'webp' => 84 );
		}
	}

	/**
	 * Normalize quality value.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $fallback Fallback.
	 * @return int
	 */
	private static function normalize_quality( $value, $fallback ) {
		$value = absint( $value );
		if ( $value < 40 || $value > 100 ) {
			return $fallback;
		}
		return $value;
	}

	/**
	 * Normalize dimension.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $fallback Fallback.
	 * @return int
	 */
	private static function normalize_dimension( $value, $fallback ) {
		$value = absint( $value );
		if ( 0 === $value ) {
			return 0;
		}
		if ( $value < 320 || $value > 10000 ) {
			return $fallback;
		}
		return $value;
	}
}
