<?php
namespace PPrev\Preview;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP;

/**
 * Immutable representation of the incoming preview request.
 */
class PreviewRequest {
	/**
	 * @var WP
	 */
	private $wp;

	/**
	 * @var string|null
	 */
	private $token;

	/**
	 * @var array
	 */
	private $query_vars;

	/**
	 * PreviewRequest constructor.
	 *
	 * @param WP         $wp
	 * @param string|null $token
	 * @param array      $query_vars
	 */
	public function __construct( WP $wp, $token, array $query_vars ) {
		$this->wp         = $wp;
		$this->token      = $token;
		$this->query_vars = $query_vars;
	}

	/**
	 * Builds the request from WP globals.
	 *
	 * @param WP $wp
	 *
	 * @return static
	 */
	public static function from_wp( WP $wp ) {
		$token      = isset( $wp->query_vars['_ppp'] )
			? sanitize_text_field( wp_unslash( (string) $wp->query_vars['_ppp'] ) )
			: null;
		$query_vars = is_array( $wp->query_vars ) ? $wp->query_vars : array();

		return new static( $wp, $token, $query_vars );
	}

	/**
	 * Returns the nonce/token supplied via `_ppp`.
	 *
	 * @return string|null
	 */
	public function token(): ?string {
		return $this->token;
	}

	/**
	 * Returns the WP request instance.
	 *
	 * @return WP
	 */
	public function wp(): WP {
		return $this->wp;
	}

	/**
	 * Returns query vars.
	 *
	 * @return array
	 */
	public function query_vars(): array {
		return $this->query_vars;
	}
}

