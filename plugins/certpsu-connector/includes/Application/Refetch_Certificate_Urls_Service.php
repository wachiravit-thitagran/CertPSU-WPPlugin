<?php
/**
 * Refetch urls.
 *
 * @package CertPSU\Connector\Application
 */

declare(strict_types=1);

namespace CertPSU\Connector\Application;

/**
 * Polls URLs.
 */
final class Refetch_Certificate_Urls_Service {

	/**
	 * Constructor.
	 *
	 * @param \CertPSU\Connector\Database\Repositories\Issuance_Repository    $issuances Issuances repo.
	 * @param \CertPSU\Connector\Database\Repositories\Certificate_Repository $certificates Certs repo.
	 * @param \CertPSU\Connector\CertPSU\CertPSU_Api_Client                   $client Client.
	 * @param \CertPSU\Connector\CertPSU\Participant_Response_Normalizer      $normalizer Normalizer.
	 * @param \CertPSU\Connector\Queue\Queue                                  $queue Queue.
	 */
	public function __construct(
		private \CertPSU\Connector\Database\Repositories\Issuance_Repository $issuances,
		private \CertPSU\Connector\Database\Repositories\Certificate_Repository $certificates,
		private \CertPSU\Connector\CertPSU\CertPSU_Api_Client $client,
		private \CertPSU\Connector\CertPSU\Participant_Response_Normalizer $normalizer,
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

		$response = $this->client->get_participants( (string) $issuance['certpsu_class_id'] );
		if ( ! $response->success ) {
			$this->issuances->mark_failed( $issuance_id, 'poll_urls', $response->error_code, $response->error_message );
			return;
		}

		$participants = $this->normalizer->normalize( $response->data ?? array() );
		$summary      = $this->certificates->sync_urls_from_certpsu( $issuance_id, $participants );

		if ( $summary['ready_count'] === $summary['participant_count'] ) {
			$this->issuances->mark_completed( $issuance_id );
			do_action( 'certpsu_issuance_completed', $this->issuances->find_by_id( $issuance_id ) );
			return;
		}

		$poll_count = isset( $issuance['poll_attempt_count'] ) ? (int) $issuance['poll_attempt_count'] : 0;
		if ( $poll_count >= 60 ) {
			$this->issuances->mark_terminal_poll_state( $issuance_id, $summary['ready_count'] > 0 ? 'partially_completed' : 'failed' );
			return;
		}

		$this->issuances->increment_poll_attempts( $issuance_id );
		$this->queue->schedule_poll_certificate_urls( $issuance_id, 120 );
	}
}
