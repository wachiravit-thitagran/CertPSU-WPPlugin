<?php
/**
 * Issuance Result DTO.
 *
 * @package CertPSU\Connector\DTO
 */

declare(strict_types=1);

namespace CertPSU\Connector\DTO;

/**
 * Issuance result.
 */
final class Issuance_Result {

	/**
	 * Constructor.
	 *
	 * @param int    $issuance_id Issuance ID.
	 * @param string $status Status.
	 * @param bool   $is_existing Existing flag.
	 * @param string $external_source Source.
	 * @param string $external_id External ID.
	 * @param int    $participant_count Count.
	 */
	public function __construct(
		public readonly int $issuance_id,
		public readonly string $status,
		public readonly bool $is_existing,
		public readonly string $external_source,
		public readonly string $external_id,
		public readonly int $participant_count
	) {}

	/**
	 * To array.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'success'           => true,
			'issuance_id'       => $this->issuance_id,
			'status'            => $this->status,
			'is_existing'       => $this->is_existing,
			'external_source'   => $this->external_source,
			'external_id'       => $this->external_id,
			'participant_count' => $this->participant_count,
		);
	}
}
