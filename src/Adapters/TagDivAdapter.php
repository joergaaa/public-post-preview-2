<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace PPrev\Adapters;

use PPrev\Preview\PreviewContext;
use WP_Query;

class TagDivAdapter implements AdapterInterface {

	/**
	 * Detects TagDiv environment.
	 *
	 * @param PreviewContext $context
	 *
	 * @return bool
	 */
	public function supports( PreviewContext $context ): bool {
		return class_exists( 'tdb_state_loader', false ) || class_exists( 'td_global', false );
	}

	public function bootstrap( PreviewContext $context ): void {
		// Sanitize post ID before setting in superglobals for TagDiv compatibility.
		$post_id = absint( $context->post()->ID );
		$_GET['td_preview_post_id']     = $post_id;
		$_REQUEST['td_preview_post_id'] = $post_id;
	}

	public function finalize( PreviewContext $context, WP_Query $preview_query ): string {
		// For now we rely on TagDiv falling back to default swap.
		return AdapterInterface::NOT_HANDLED;
	}
}

