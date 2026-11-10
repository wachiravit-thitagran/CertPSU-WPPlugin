<?php
/**
 * Retry Failed Step Service.
 *
 * @package CertPSU\Connector\Application
 */

declare(strict_types=1);

namespace CertPSU\Connector\Application;

/**
 * Retries failed step.
 */
final class Retry_Failed_Step_Service {

	/**
	 * Constructor.
	 *
	 * @param \CertPSU\Connector\Database\Repositories\Issuance_Repository $issuances Issuances repo.
	 * @param \CertPSU\Connector\Queue\Queue                               $queue Queue.
	 */
	public function __construct(
		private \CertPSU\Connector\Database\Repositories\Issuance_Repository $issuances,
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
		if ( ! $issuance || empty( $issuance['failed_step'] )
			|| 'remote_outcome_unknown' === ( $issuance['last_error_code'] ?? '' )
			|| ( 'poll_urls' === ( $issuance['current_step'] ?? '' ) && (int) ( $issuance['poll_attempt_count'] ?? 0 ) >= 60 )
			|| in_array( $issuance['status'] ?? '', array( 'completed', 'partially_completed', 'released' ), true ) ) {
			return;
		}

		$failed_step = (string) $issuance['failed_step'];
		if ( 'release' === $failed_step ) {
			$scheduled = $this->queue->enqueue_release_issuance( $issuance_id );
		} elseif ( 'poll_urls' === $failed_step ) {
			$scheduled = $this->queue->enqueue_refetch_certificate_urls( $issuance_id );
		} else {
			$scheduled = $this->queue->enqueue_process_issuance( $issuance_id );
		}
		if ( ! $scheduled ) {
			$this->issuances->mark_failed( $issuance_id, $failed_step, 'queue_failed', 'Retry could not be scheduled. The saved workflow checkpoint is preserved; retry once scheduling is available.' );
			throw new \RuntimeException( 'CertPSU retry could not be scheduled.' );
		}
	}
}
