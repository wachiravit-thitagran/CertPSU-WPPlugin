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
		if ( ! $this->issuances->acquire_worker_claim( $issuance_id ) ) {
			if ( ! $this->queue->schedule_release_issuance( $issuance_id, 30 ) ) {
				throw new \RuntimeException( 'CertPSU release claim is busy and deferred delivery could not be scheduled.' );
			}
			return;
		}
		try {
			$this->release( $issuance_id );
		} finally {
			$this->issuances->release_worker_claim( $issuance_id );
		}
	}

	private function release( int $issuance_id ): void {
		$issuance = $this->issuances->find_by_id( $issuance_id );
		if ( ! $issuance || empty( $issuance['certpsu_class_id'] )
			|| 'remote_outcome_unknown' === ( $issuance['last_error_code'] ?? '' )
			|| ! in_array( $issuance['status'], array( 'processing', 'waiting_for_release', 'failed' ), true )
			|| ( 'failed' === $issuance['status'] ? 'release' !== ( $issuance['failed_step'] ?? '' )
				: ! in_array( $issuance['current_step'] ?? '', array( 'add_participants', 'waiting_for_release' ), true ) ) ) {
			return;
		}
		if ( ! $this->issuances->mark_remote_outcome_unknown( $issuance_id, 'release' ) ) {
			return;
		}

		$response = $this->client->release_participants( (string) $issuance['certpsu_class_id'] );
		if ( ! $response->success ) {
			if ( $response->status_code >= 400 && $response->status_code < 500 && ! in_array( $response->status_code, array( 408, 409 ), true ) ) {
				$this->issuances->mark_failed( $issuance_id, 'release', $response->error_code, $response->error_message );
			}
			do_action( 'certpsu_issuance_failed', $this->issuances->find_by_id( $issuance_id ) );
			return;
		}

		if ( ! $this->issuances->save_workflow_checkpoint( $issuance_id, array(
			'status' => 'released', 'current_step' => 'release', 'released_at' => current_time( 'mysql', true ),
			'failed_step' => null, 'last_error_code' => null, 'last_error_message' => null,
		) ) ) {
			return;
		}
		$this->certificates->mark_all_for_issuance( $issuance_id, 'released' );
		do_action( 'certpsu_issuance_released', $this->issuances->find_by_id( $issuance_id ) );
		if ( ! $this->queue->schedule_poll_certificate_urls( $issuance_id, 60 ) ) {
			$this->issuances->mark_failed( $issuance_id, 'poll_urls', 'queue_failed', 'Certificate polling could not be scheduled. Retry this failed step to resume polling without replaying release.' );
		}
	}
}
