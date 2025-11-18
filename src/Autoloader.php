<?php

/**
 * Simple PSR-4 style autoloader for the PPP namespace.
 *
 * @package PublicPostPreview
 */

namespace PPP;

class Autoloader {

	/**
	 * Namespace prefix to directory map.
	 *
	 * @var array<string, string>
	 */
	private $prefixes = array();

	/**
	 * Registers the autoloader instance.
	 *
	 * @param string $prefix Namespace prefix (e.g. 'PPP\\').
	 * @param string $base_dir Absolute path to the source directory.
	 */
	public function register( $prefix, $base_dir ) {
		$prefix                  = trim( $prefix, '\\' ) . '\\';
		$this->prefixes[ $prefix ] = rtrim( $base_dir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;

		if ( ! spl_autoload_register( array( $this, 'autoload' ) ) ) {
			throw new \RuntimeException( 'Unable to register PPP autoloader.' );
		}
	}

	/**
	 * Attempts to autoload a class.
	 *
	 * @param string $class Fully-qualified class name.
	 */
	private function autoload( $class ) {
		foreach ( $this->prefixes as $prefix => $base_dir ) {
			if ( 0 !== strpos( $class, $prefix ) ) {
				continue;
			}

			$relative_class = substr( $class, strlen( $prefix ) );
			$file           = $base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	}
}

