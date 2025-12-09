<?php
namespace PPrev\Adapters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PPrev\Preview\PreviewContext;
use WP_Query;

interface AdapterInterface {

	const HANDLED = 'handled';
	const NOT_HANDLED = 'not_handled';

	/**
	 * Determines whether the adapter should run for the given context.
	 *
	 * @param PreviewContext $context
	 *
	 * @return bool
	 */
	public function supports( PreviewContext $context ): bool;

	/**
	 * Early bootstrap hook before WordPress performs template_redirect.
	 *
	 * @param PreviewContext $context
	 * @return void
	 */
	public function bootstrap( PreviewContext $context ): void;

	/**
	 * Finalizes the preview by integrating with builder/state layers.
	 *
	 * @param PreviewContext $context
	 * @param WP_Query       $preview_query
	 *
	 * @return string One of the AdapterInterface::HANDLED constants.
	 */
	public function finalize( PreviewContext $context, WP_Query $preview_query ): string;
}

