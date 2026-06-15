<?php
/**
 * Participant normalizer.
 *
 * @package CertPSU\Connector\CertPSU
 */

declare(strict_types=1);

namespace CertPSU\Connector\CertPSU;

/**
 * Normalizes participant response.
 */
final class Participant_Response_Normalizer {

	/**
	 * Normalize payload.
	 *
	 * @param array<string,mixed> $payload API Payload.
	 * @return array<int,array<string,mixed>>
	 */
	public function normalize( array $payload ): array {
		$participants = isset( $payload['participants'] ) && is_array( $payload['participants'] ) ? $payload['participants'] : array();
		if ( array_is_list( $participants ) ) {
			/**
			 * Participants list.
			 *
			 * @var array<int,array<string,mixed>> $participants
			 */
			return $participants;
		}

		$normalized = array();
		foreach ( $participants as $participant_id => $participant ) {
			if ( ! is_array( $participant ) ) {
				continue;
			}
			$participant['id'] = $participant['id'] ?? $participant_id;
			$normalized[]      = $participant;
		}

		return $normalized;
	}
}
