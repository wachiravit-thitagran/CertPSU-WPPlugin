<?php
/**
 * JSON support.
 *
 * @package CertPSU\Connector\Support
 */

declare(strict_types=1);

namespace CertPSU\Connector\Support;

/**
 * JSON Helper.
 */
final class Json {

	/**
	 * Encode.
	 *
	 * @param array<mixed> $data Data.
	 * @return string
	 */
	public static function encode( array $data ): string {
		return (string) wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Decode.
	 *
	 * @param string $json Json.
	 * @return array<mixed>
	 */
	public static function decode( string $json ): array {
		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? $decoded : array();
	}
}
