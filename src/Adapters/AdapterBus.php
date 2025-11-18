<?php

namespace PPP\Adapters;

use PPP\Contracts\LoggerInterface;
use PPP\Preview\PreviewContext;
use WP_Query;

class AdapterBus {

	/**
	 * @var AdapterInterface[]
	 */
	private $adapters = array();

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	public function __construct( array $adapters, LoggerInterface $logger ) {
		$this->adapters = $adapters;
		$this->logger   = $logger;
	}

	/**
	 * Runs bootstrap on supporting adapters.
	 *
	 * @param PreviewContext $context
	 *
	 * @return void
	 */
	public function bootstrap( PreviewContext $context ) {
		foreach ( $this->adapters as $adapter ) {
			if ( ! $adapter->supports( $context ) ) {
				continue;
			}

			$adapter->bootstrap( $context );
		}
	}

	/**
	 * Runs finalize on supporting adapters until one handles the swap.
	 *
	 * @param PreviewContext $context
	 * @param WP_Query       $preview_query
	 *
	 * @return bool True when at least one adapter handled the swap.
	 */
	public function finalize( PreviewContext $context, WP_Query $preview_query ) {
		foreach ( $this->adapters as $adapter ) {
			if ( ! $adapter->supports( $context ) ) {
				continue;
			}

			$result = $adapter->finalize( $context, $preview_query );

			if ( AdapterInterface::HANDLED === $result ) {
				$this->logger->info(
					'Preview swap handled by adapter',
					array(
						'adapter'    => get_class( $adapter ),
						'post_id'    => $context->post()->ID,
						'request_id' => $context->request_id(),
					)
				);

				return true;
			}
		}

		return false;
	}
}

