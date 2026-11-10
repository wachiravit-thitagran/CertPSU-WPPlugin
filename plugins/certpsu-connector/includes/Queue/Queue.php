<?php
/**
 * Queue.
 *
 * @package CertPSU\Connector\Queue
 */

declare(strict_types=1);

namespace CertPSU\Connector\Queue;

/**
 * Queue system.
 */
class Queue {

	/** Defer contended delivery rather than consuming its only queued action. */
	public function schedule_process_issuance( int $issuance_id, int $delay_seconds ): bool {
		$id = \as_schedule_single_action( time() + $delay_seconds, 'certpsu_connector_process_issuance', array( 'issuance_id' => $issuance_id ), 'certpsu-connector' ); // @phpstan-ignore-line -- Action Scheduler runtime function.
		return is_int( $id ) && $id > 0;
	}

	/** Retain release delivery when another worker owns the issuance. */
	public function schedule_release_issuance( int $issuance_id, int $delay_seconds ): bool {
		$id = \as_schedule_single_action( time() + $delay_seconds, 'certpsu_connector_release_issuance', array( 'issuance_id' => $issuance_id ), 'certpsu-connector' ); // @phpstan-ignore-line -- Action Scheduler runtime function.
		return is_int( $id ) && $id > 0;
	}

	/**
	 * Enqueue process issuance.
	 *
	 * @param int $issuance_id ID.
	 * @return bool
	 */
	public function enqueue_process_issuance( int $issuance_id ): bool {
		$id = \as_enqueue_async_action( 'certpsu_connector_process_issuance', array( 'issuance_id' => $issuance_id ), 'certpsu-connector' ); // @phpstan-ignore-line
		return is_int( $id ) && $id > 0;
	}

	/**
	 * Enqueue release issuance.
	 *
	 * @param int $issuance_id ID.
	 * @return bool
	 */
	public function enqueue_release_issuance( int $issuance_id ): bool {
		$id = \as_enqueue_async_action( 'certpsu_connector_release_issuance', array( 'issuance_id' => $issuance_id ), 'certpsu-connector' ); // @phpstan-ignore-line
		return is_int( $id ) && $id > 0;
	}

	/**
	 * Enqueue retry failed step.
	 *
	 * @param int $issuance_id ID.
	 * @return bool
	 */
	public function enqueue_retry_failed_step( int $issuance_id ): bool {
		$id = \as_enqueue_async_action( 'certpsu_connector_retry_failed_step', array( 'issuance_id' => $issuance_id ), 'certpsu-connector' ); // @phpstan-ignore-line
		return is_int( $id ) && $id > 0;
	}

	/**
	 * Enqueue refetch certificate urls.
	 *
	 * @param int $issuance_id ID.
	 * @return bool
	 */
	public function enqueue_refetch_certificate_urls( int $issuance_id ): bool {
		$id = \as_enqueue_async_action( 'certpsu_connector_refetch_certificate_urls', array( 'issuance_id' => $issuance_id ), 'certpsu-connector' ); // @phpstan-ignore-line
		return is_int( $id ) && $id > 0;
	}

	/**
	 * Schedule poll.
	 *
	 * @param int $issuance_id ID.
	 * @param int $delay_seconds Delay.
	 * @return bool
	 */
	public function schedule_poll_certificate_urls( int $issuance_id, int $delay_seconds ): bool {
		$id = \as_schedule_single_action( time() + $delay_seconds, 'certpsu_connector_poll_certificate_urls', array( 'issuance_id' => $issuance_id ), 'certpsu-connector' ); // @phpstan-ignore-line
		return is_int( $id ) && $id > 0;
	}
}
