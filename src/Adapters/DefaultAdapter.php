<?php

namespace PPP\Adapters;

use PPP\Preview\PreviewContext;
use WP_Query;

/**
 * Fallback adapter that swaps the global query for standard themes.
 */
class DefaultAdapter implements AdapterInterface {

	public function supports( PreviewContext $context ): bool {
		return true;
	}

	public function bootstrap( PreviewContext $context ): void {
		// Nothing to do yet.
	}

	public function finalize( PreviewContext $context, WP_Query $preview_query ): string {
		global $wp_query;

		$wp_query = $preview_query;
		$GLOBALS['post'] = $preview_query->post;
		setup_postdata( $preview_query->post );

		return AdapterInterface::HANDLED;
	}
}

