<?php

namespace PPP\Preview;

use PPP\Contracts\LoggerInterface;
use PPP\Repository\PreviewTokenRepository;
use PPP\Security\PreviewNonceValidator;
use WP_Post;

class PreviewResolver {

	/**
	 * @var PreviewTokenRepository
	 */
	private $repository;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var PreviewNonceValidator
	 */
	private $nonce_validator;

	public function __construct( PreviewTokenRepository $repository, PreviewNonceValidator $nonce_validator, LoggerInterface $logger ) {
		$this->repository = $repository;
		$this->nonce_validator = $nonce_validator;
		$this->logger     = $logger;
	}

	/**
	 * Attempts to build a preview context from the request.
	 *
	 * @param PreviewRequest $request
	 *
	 * @return PreviewContext|null
	 */
	public function resolve( PreviewRequest $request ): ?PreviewContext {
		$token = $request->token();

		if ( empty( $token ) ) {
			return null;
		}

		$post_id = $this->resolve_post_id( $request );

		if ( ! $post_id ) {
			$this->logger->warning( 'No preview post id detected', array( 'token' => $token ) );
			return null;
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			$this->logger->warning( 'Preview post could not be loaded', array( 'post_id' => $post_id ) );
			return null;
		}

		if ( ! $this->repository->is_enabled( $post_id ) ) {
			$this->logger->warning( 'Preview attempted for post without public preview enabled', array( 'post_id' => $post_id ) );
			return null;
		}

		if ( ! $this->nonce_validator->is_valid( $token, $post_id ) ) {
			$this->logger->warning( 'Preview token invalid', array( 'post_id' => $post_id ) );
			return null;
		}

		$request_id = $this->generate_request_id( $post_id );

		$this->logger->info(
			'Preview context resolved',
			array(
				'post_id'    => $post_id,
				'request_id' => $request_id,
			)
		);

		return new PreviewContext( $post, $token, $request_id );
	}

	/**
	 * Resolves the preview post ID from request/query parameters.
	 *
	 * @param PreviewRequest $request
	 *
	 * @return int
	 */
	private function resolve_post_id( PreviewRequest $request ): int {
		$candidates = array();
		$query_vars = $request->query_vars();

		foreach ( array( 'p', 'page_id', 'post_id' ) as $key ) {
			if ( isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$candidates[] = absint( $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}

		foreach ( array( 'p', 'page_id' ) as $key ) {
			if ( ! empty( $query_vars[ $key ] ) ) {
				$candidates[] = absint( $query_vars[ $key ] );
			}
		}

		if ( ! empty( $query_vars['post__in'] ) && is_array( $query_vars['post__in'] ) ) {
			foreach ( $query_vars['post__in'] as $id ) {
				$candidates[] = absint( $id );
			}
		}

		foreach ( $candidates as $candidate ) {
			if ( $candidate > 0 ) {
				return $candidate;
			}
		}

		return 0;
	}

	/**
	 * Generates a correlation ID.
	 *
	 * @param int $post_id
	 *
	 * @return string
	 */
	private function generate_request_id( int $post_id ): string {
		return sprintf(
			'PPP-%s-%d-%s',
			wp_date( 'Ymd-His' ),
			$post_id,
			substr( wp_hash( uniqid( (string) $post_id, true ) ), 0, 8 )
		);
	}
}

