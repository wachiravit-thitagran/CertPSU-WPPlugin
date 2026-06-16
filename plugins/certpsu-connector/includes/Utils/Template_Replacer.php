<?php
/**
 * Template string replacement engine.
 *
 * @package CertPSU\Connector\Utils
 */

declare(strict_types=1);

namespace CertPSU\Connector\Utils;

/**
 * Replaces placeholders in strings and arrays.
 */
final class Template_Replacer {

	/**
	 * Replace {placeholders} in a string with values from context.
	 *
	 * @param string               $template String with placeholders.
	 * @param array<string,scalar> $context  Dictionary of placeholder values.
	 * @return string
	 */
	public function replace( string $template, array $context ): string {
		return preg_replace_callback(
			'/\{([a-zA-Z0-9_]+)\}/',
			function ( array $matches ) use ( $context ): string {
				$key = $matches[1];
				if ( array_key_exists( $key, $context ) ) {
					return (string) $context[ $key ];
				}
				// Leave unmodified if no context provided.
				return $matches[0];
			},
			$template
		) ?? $template;
	}

	/**
	 * Recursively apply replacements to all strings in an array payload.
	 *
	 * @param array<string,mixed>  $data    The payload array.
	 * @param array<string,scalar> $context Dictionary of placeholder values.
	 * @return array<string,mixed>
	 */
	public function replace_recursive( array $data, array $context ): array {
		if ( empty( $context ) ) {
			return $data;
		}

		$result = array();
		foreach ( $data as $key => $value ) {
			if ( is_string( $value ) ) {
				$result[ $key ] = $this->replace( $value, $context );
			} elseif ( is_array( $value ) ) {
				$result[ $key ] = $this->replace_recursive( $value, $context );
			} else {
				$result[ $key ] = $value;
			}
		}

		return $result;
	}
}
