<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace PPrev\Security;

class PreviewNonceValidator {

	/**
	 * Validates the preview token using PPP's extended lifetime rules.
	 *
	 * @param string $token
	 * @param int    $post_id
	 *
	 * @return bool
	 */
	public function is_valid( string $token, int $post_id ): bool {
		if ( empty( $token ) || $post_id <= 0 ) {
			return false;
		}

		$action = 'public_post_preview_' . $post_id;
		$i      = $this->nonce_tick();

		// Nonce generated 0-<lifetime>/2 hours ago.
		if ( substr( wp_hash( $i . $action, 'nonce' ), -12, 10 ) === $token ) {
			return true;
		}

		// Nonce generated earlier within one tick.
		if ( substr( wp_hash( ( $i - 1 ) . $action, 'nonce' ), -12, 10 ) === $token ) {
			return true;
		}

		return false;
	}

	/**
	 * Mirrors PPrev_Public_Post_Preview::nonce_tick().
	 *
	 * Uses the same logic as the legacy implementation to ensure compatibility.
	 * The `pprev_nonce_life` filter can be used to customize the nonce lifetime.
	 *
	 * @return int
	 */
	private function nonce_tick(): int {
		$expiration = get_option( 'public_post_preview_expiration_time' );

		if ( ! $expiration ) {
			$expiration = 48; // hours.
		}

		// Apply filter to allow customization of nonce lifetime (in seconds).
		// Example: add_filter( 'pprev_nonce_life', function() { return 5 * DAY_IN_SECONDS; } );
		$nonce_life = apply_filters( 'pprev_nonce_life', $expiration * HOUR_IN_SECONDS );

		return ceil( time() / ( $nonce_life / 2 ) );
	}
}

