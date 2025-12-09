<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace PPrev\Preview;

use WP_Post;

/**
 * Carries the resolved preview state across services.
 */
class PreviewContext {

	/**
	 * @var WP_Post
	 */
	private $post;

	/**
	 * @var string
	 */
	private $token;

	/**
	 * @var string
	 */
	private $request_id;

	/**
	 * PreviewContext constructor.
	 *
	 * @param WP_Post $post
	 * @param string  $token
	 * @param string  $request_id
	 */
	public function __construct( WP_Post $post, $token, $request_id ) {
		$this->post       = $post;
		$this->token      = $token;
		$this->request_id = $request_id;
	}

	/**
	 * Returns the previewed post.
	 *
	 * @return WP_Post
	 */
	public function post(): WP_Post {
		return $this->post;
	}

	/**
	 * Returns the token/nonce.
	 *
	 * @return string
	 */
	public function token(): string {
		return $this->token;
	}

	/**
	 * Returns the correlation ID for logging.
	 *
	 * @return string
	 */
	public function request_id(): string {
		return $this->request_id;
	}
}

