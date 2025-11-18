<?php

namespace PPP\Infrastructure;

use PPP\Contracts\LoggerInterface;

/**
 * Basic logger that writes to error_log with a PPP prefix.
 */
class WpLogger implements LoggerInterface {

	/**
	 * @var string
	 */
	private $log_file;

	public function __construct( $log_file = null ) {
		$this->log_file = $log_file ?: dirname( __DIR__, 2 ) . '/preview-debug.log';
	}

	/**
	 * @inheritDoc
	 */
	public function info( $message, array $context = array() ) {
		// Log info messages for debugging purposes.
		$this->log( 'INFO', $message, $context );
	}

	/**
	 * @inheritDoc
	 */
	public function warning( $message, array $context = array() ) {
		$this->log( 'WARNING', $message, $context );
	}

	/**
	 * @inheritDoc
	 */
	public function error( $message, array $context = array() ) {
		$this->log( 'ERROR', $message, $context );
	}

	/**
	 * Writes to the PHP error log.
	 *
	 * @param string $level
	 * @param string $message
	 * @param array  $context
	 */
	protected function log( $level, $message, array $context = array() ) {
		if ( empty( $message ) ) {
			return;
		}

		$entry = sprintf(
			'[PPP][%s] %s %s',
			$level,
			$message,
			empty( $context ) ? '' : wp_json_encode( $context )
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Direct filesystem access needed for debug logging.
		if ( $this->log_file && is_writable( dirname( $this->log_file ) ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Direct filesystem access needed for debug logging.
			file_put_contents( $this->log_file, $entry . PHP_EOL, FILE_APPEND );
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Fallback logging when file write fails.
			error_log( $entry );
		}
	}
}

