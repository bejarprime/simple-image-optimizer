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
