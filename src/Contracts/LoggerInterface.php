<?php
namespace PPrev\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal logger contract (subset of PSR-3) so we can swap implementations later.
 */
interface LoggerInterface {
	/**
	 * Adds an informational log entry.
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public function info( $message, array $context = array() );

	/**
	 * Adds a warning log entry.
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public function warning( $message, array $context = array() );

	/**
	 * Adds an error log entry.
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public function error( $message, array $context = array() );
}

