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
}
