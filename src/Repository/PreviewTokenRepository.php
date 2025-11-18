<?php

namespace PPP\Repository;

/**
 * Temporary repository that still relies on the legacy option storage.
 */
class PreviewTokenRepository {

	const OPTION = 'public_post_preview';

	/**
	 * Checks whether a given post ID is registered for public preview.
	 *
	 * @param int $post_id
	 *
	 * @return bool
	 */
	public function is_enabled( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return false;
		}

		$ids = $this->all();

		return in_array( $post_id, $ids, true );
	}

	/**
	 * Returns all registered IDs (legacy behaviour).
	 *
	 * @return int[]
	 */
	public function all() {
		$ids = get_option( self::OPTION, array() );

		if ( ! is_array( $ids ) ) {
			return array();
		}

		return array_values(
			array_unique(
				array_map( 'intval', $ids )
			)
		);
	}
}

