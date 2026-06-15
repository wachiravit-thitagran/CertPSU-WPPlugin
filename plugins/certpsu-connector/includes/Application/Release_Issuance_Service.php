<?php
/**
 * Release Issuance Service.
 *
 * @package CertPSU\Connector\Application
 */

declare(strict_types=1);

namespace CertPSU\Connector\Application;

/**
 * Releases issuance.
 */
final class Release_Issuance_Service {

	/**
	 * Constructor.
	 *
	 * @param \CertPSU\Connector\Database\Repositories\Issuance_Repository    $issuances Issuances repo.
	 * @param \CertPSU\Connector\Database\Repositories\Certificate_Repository $certificates Certs repo.
	 * @param \CertPSU\Connector\CertPSU\CertPSU_Api_Client                   $client Client.
	 * @param \CertPSU\Connector\Queue\Queue                                  $queue Queue.
	 */
	public function __construct(
		private \CertPSU\Connector\Database\Repositories\Issuance_Repository $issuances,
		private \CertPSU\Connector\Database\Repositories\Certificate_Repository $certificates,
		private \CertPSU\Connector\CertPSU\CertPSU_Api_Client $client,
		private \CertPSU\Connector\Queue\Queue $queue
	) {}

	/**
	 * Handle.
	 *
	 * @param int $issuance_id ID.
	 * @return void
	 */
	public function handle( int $issuance_id ): void {
		$issuance = $this->issuances->find_by_id( $issuance_id );
		if ( ! $issuance || empty( $issuance['certpsu_class_id'] ) ) {
			return;
		}

		$response = $this->client->release_participants( (string) $issuance['certpsu_class_id'] );
		if ( ! $response->success ) {
			$this->issuances->mark_failed( $issuance_id, 'release', $response->error_code, $response->error_message );
			return;
		}

		$this->issuances->mark_released( $issuance_id );
		$this->certificates->mark_all_for_issuance( $issuance_id, 'released' );
		do_action( 'certpsu_issuance_released', $this->issuances->find_by_id( $issuance_id ) );
		$this->queue->schedule_poll_certificate_urls( $issuance_id, 60 );
	}
}
