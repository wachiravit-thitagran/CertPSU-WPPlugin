<?php
/**
 * Payload Sanitizer.
 *
 * @package CertPSU\Connector\Support
 */

declare(strict_types=1);

namespace CertPSU\Connector\Support;

/**
 * Sanitizes payloads before logging.
 */
final class Payload_Sanitizer {

	/**
	 * Sanitize payload.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return array<string,mixed>
	 */
	public function sanitize( array $payload ): array {
		$sanitized = array();
		foreach ( $payload as $key => $value ) {
			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize( $value );
				continue;
			}

			if ( preg_match( '/key|token|secret|password/i', (string) $key ) ) {
				$sanitized[ $key ] = '***';
				continue;
			}

			$sanitized[ $key ] = $value;
		}

		return $sanitized;
	}
}
