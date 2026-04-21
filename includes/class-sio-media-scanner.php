<?php
/**
 * Media library scanner.
 *
 * @package Simple_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds candidate image attachments.
 */
class SIO_Media_Scanner {
	/** Supported MIME types. */
	const SUPPORTED_MIME_TYPES = array( 'image/jpeg', 'image/png' );

	/**
	 * Scan media library by page.
	 *
	 * @param int  $page Page number.
	 * @param int  $per_page Items per page.
	 * @param bool $include_optimized Include already optimized images.
	 * @return array
	 */
	public function scan( $page = 1, $per_page = 100, $include_optimized = false ) {
		$page     = max( 1, absint( $page ) );
		$per_page = min( 200, max( 1, absint( $per_page ) ) );

		$args = array(
			'post_type'              => 'attachment',
			'post_status'            => 'inherit',
			'post_mime_type'         => self::SUPPORTED_MIME_TYPES,
			'fields'                 => 'ids',
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		if ( ! $include_optimized ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array(
					'key'     => '_sio_optimized',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_sio_optimized',
					'value'   => '1',
					'compare' => '!=',
				),
			);
		}

		$query = new WP_Query( $args );
		$ids   = array_map( 'absint', $query->posts );
		$items = array();

		foreach ( $ids as $id ) {
			$item = $this->get_candidate_info( $id );
			if ( $item ) {
				$items[] = $item;
			}
		}

		return array(
			'items'       => $items,
			'ids'         => wp_list_pluck( $items, 'id' ),
			'total'       => absint( $query->found_posts ),
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => absint( $query->max_num_pages ),
		);
	}

	/**
	 * Get lightweight candidate data.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|null
	 */
	public function get_candidate_info( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		$mime          = get_post_mime_type( $attachment_id );

		if ( ! in_array( $mime, self::SUPPORTED_MIME_TYPES, true ) ) {
			return null;
		}

		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! file_exists( $path ) ) {
			return null;
		}

		$size = filesize( $path );
		$meta = wp_get_attachment_metadata( $attachment_id );

		return array(
			'id'        => $attachment_id,
			'title'     => get_the_title( $attachment_id ),
			'mime'      => $mime,
			'filename'  => wp_basename( $path ),
			'bytes'     => false === $size ? 0 : (int) $size,
			'width'     => isset( $meta['width'] ) ? absint( $meta['width'] ) : 0,
			'height'    => isset( $meta['height'] ) ? absint( $meta['height'] ) : 0,
			'optimized' => '1' === (string) get_post_meta( $attachment_id, '_sio_optimized', true ),
		);
	}
}
