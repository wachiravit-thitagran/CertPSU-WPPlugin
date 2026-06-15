<?php
/**
 * PHPUnit bootstrap for plugin tests.
 *
 * @package CertPSU\Connector\Tests
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	/**
	 * Provide a minimal plugin_dir_path() stub for unit tests.
	 *
	 * @param string $file Plugin file path.
	 *
	 * @return string
	 */
	function plugin_dir_path( string $file ): string {
		return dirname( $file ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	/**
	 * Provide a minimal plugin_dir_url() stub for unit tests.
	 *
	 * @param string $file Plugin file path.
	 *
	 * @return string
	 */
	function plugin_dir_url( string $file ): string {
		return 'http://example.org/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Minimal sanitize_text_field() stub.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	function sanitize_text_field( $value ): string {
		return is_string( $value ) ? trim( (string) preg_replace( '/[\r\n\t ]+/', ' ', $value ) ) : '';
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	/**
	 * Minimal sanitize_textarea_field() stub.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	function sanitize_textarea_field( $value ): string {
		return is_string( $value ) ? trim( $value ) : '';
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	/**
	 * Minimal get_the_title() stub.
	 *
	 * @param int $id Post ID.
	 * @return string
	 */
	function get_the_title( $id ): string {
		return 'Course ' . (int) $id;
	}
}
