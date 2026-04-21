<?php
/**
 * Uninstall handler.
 *
 * @package Simple_Image_Optimizer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = get_option( 'simple_image_optimizer_options', array() );

if ( ! empty( $options['delete_on_uninstall'] ) ) {
	delete_option( 'simple_image_optimizer_options' );
	delete_option( 'simple_image_optimizer_stats' );
	delete_option( 'simple_image_optimizer_recent_results' );

	global $wpdb;

	$meta_keys = array(
		'_sio_optimized',
		'_sio_original_size',
		'_sio_optimized_size',
		'_sio_webp_path',
		'_sio_backup_path',
		'_sio_last_error',
	);

	foreach ( $meta_keys as $meta_key ) {
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $meta_key ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
