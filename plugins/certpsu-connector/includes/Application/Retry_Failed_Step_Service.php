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
		if ( ! $issuance || empty( $issuance['failed_step'] ) ) {
			return;
		}

		$failed_step = (string) $issuance['failed_step'];
		if ( 'release' === $failed_step ) {
			$this->queue->enqueue_release_issuance( $issuance_id );
		} elseif ( 'poll_urls' === $failed_step ) {
			$this->queue->enqueue_refetch_certificate_urls( $issuance_id );
		} else {
			$this->queue->enqueue_process_issuance( $issuance_id );
		}
	}
}
