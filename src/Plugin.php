<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace PPrev;

use PPrev\Adapters\AdapterBus;
use PPrev\Adapters\DefaultAdapter;
use PPrev\Adapters\TagDivAdapter;
use PPrev\Contracts\LoggerInterface;
use PPrev\Infrastructure\WpLogger;
use PPrev\Preview\PreviewController;
use PPrev\Preview\PreviewQueryFactory;
use PPrev\Preview\PreviewResolver;
use PPrev\Repository\PreviewTokenRepository;
use PPrev\Security\PreviewNonceValidator;

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
		// Let WpLogger use default uploads directory path.
		return new WpLogger();
	}
}

