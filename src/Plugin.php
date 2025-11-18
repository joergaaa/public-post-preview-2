<?php

namespace PPP;

use PPP\Adapters\AdapterBus;
use PPP\Adapters\DefaultAdapter;
use PPP\Adapters\TagDivAdapter;
use PPP\Contracts\LoggerInterface;
use PPP\Infrastructure\WpLogger;
use PPP\Preview\PreviewController;
use PPP\Preview\PreviewQueryFactory;
use PPP\Preview\PreviewResolver;
use PPP\Repository\PreviewTokenRepository;
use PPP\Security\PreviewNonceValidator;

class Plugin {

	/**
	 * Boots the next-gen PPP pipeline.
	 */
	public static function boot() {
		$logger      = self::make_logger();
		$repository  = new PreviewTokenRepository();
		$nonce_validator = new PreviewNonceValidator();
		$resolver    = new PreviewResolver( $repository, $nonce_validator, $logger );
		$query       = new PreviewQueryFactory( $logger );
		$adapter_bus = new AdapterBus(
			array(
				new TagDivAdapter(),
				new DefaultAdapter(),
			),
			$logger
		);

		$controller = new PreviewController( $resolver, $query, $adapter_bus, $logger );
		$controller->register_hooks();
	}

	/**
	 * Creates the logger instance.
	 *
	 * @return LoggerInterface
	 */
	private static function make_logger() {
		$log_file = dirname( __DIR__ ) . '/preview-debug.log';

		return new WpLogger( $log_file );
	}
}

