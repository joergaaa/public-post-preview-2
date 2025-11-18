<?php

namespace PPP\Preview;

use PPP\Contracts\LoggerInterface;
use WP_Query;

class PreviewQueryFactory {

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Builds a WP_Query object representing the previewed post.
	 *
	 * @param PreviewContext $context
	 *
	 * @return WP_Query
	 */
	public function build( PreviewContext $context ) {
		$post         = $context->post();
		$post_type    = $post->post_type;
		$post_id      = (int) $post->ID;
		$is_page      = ( 'page' === $post_type );
		$is_attachment = ( 'attachment' === $post_type );

		$query = new WP_Query();
		$query->posts             = array( $post );
		$query->post              = $post;
		$query->post_count        = 1;
		$query->found_posts       = 1;
		$query->max_num_pages     = 1;
		$query->queried_object    = $post;
		$query->queried_object_id = $post_id;

		$query->set( 'post_type', $post_type );
		$query->set( 'p', $post_id );
		$query->set( 'page_id', $is_page ? $post_id : 0 );
		$query->set( 'name', $post->post_name ?: $post_id );
		$query->set( 'post__in', array( $post_id ) );
		$query->set( 'fields', '' );
		$query->rewind_posts();

		$query->is_single            = ! $is_page && ! $is_attachment;
		$query->is_page              = $is_page;
		$query->is_attachment        = $is_attachment;
		$query->is_singular          = true;
		$query->is_preview           = true;
		$query->is_archive           = false;
		$query->is_post_type_archive = false;
		$query->is_404               = false;

		if ( method_exists( $query, 'set_queried_object' ) ) {
			$query->set_queried_object( $post );
		}

		$this->logger->info(
			'Preview query built',
			array(
				'post_id'    => $post_id,
				'post_type'  => $post_type,
				'request_id' => $context->request_id(),
			)
		);

		return $query;
	}
}

