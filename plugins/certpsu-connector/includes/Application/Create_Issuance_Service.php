<?php
/**
 * Create Issuance Service.
 *
 * @package CertPSU\Connector\Application
 */

declare(strict_types=1);

namespace CertPSU\Connector\Application;

use CertPSU\Connector\DTO\Issuance_Result;
use CertPSU\Connector\Support\Json;
use WP_Error;

/**
 * Creates issuance.
 */
final class Create_Issuance_Service {

	/**
	 * Constructor.
	 *
	 * @param \CertPSU\Connector\Support\Validator                            $validator Validator.
	 * @param \CertPSU\Connector\Database\Repositories\Issuance_Repository    $issuances Issuances repo.
	 * @param \CertPSU\Connector\Database\Repositories\Certificate_Repository $certificates Certs repo.
	 * @param \CertPSU\Connector\Queue\Queue                                  $queue Queue.
	 * @param \CertPSU\Connector\Support\Settings                             $settings Settings.
	 */
	public function __construct(
		private \CertPSU\Connector\Support\Validator $validator,
		private \CertPSU\Connector\Database\Repositories\Issuance_Repository $issuances,
		private \CertPSU\Connector\Database\Repositories\Certificate_Repository $certificates,
		private \CertPSU\Connector\Queue\Queue $queue,
		private \CertPSU\Connector\Support\Settings $settings
	) {}

	/**
	 * Handle.
	 *
	 * @param array<string,mixed> $args Args.
	 * @return Issuance_Result|WP_Error
	 */
	public function handle( array $args ): Issuance_Result|WP_Error {
		if ( '' === $this->settings->api_key() ) {
			return new WP_Error( 'certpsu_missing_api_key', 'CertPSU API key is not configured.' );
		}

		$validated = $this->validator->validate_create_issuance( $args );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$external_source = isset( $validated['external_source'] ) ? (string) $validated['external_source'] : '';
		$external_id     = isset( $validated['external_id'] ) ? (string) $validated['external_id'] : '';
		$idemp_mode      = isset( $validated['idempotency_mode'] ) ? (string) $validated['idempotency_mode'] : 'return_existing';

		$existing = $this->issuances->find_latest_by_external_ref( $external_source, $external_id );
		if ( $existing && 'return_existing' === $idemp_mode ) {
			return new Issuance_Result( (int) $existing['id'], (string) $existing['status'], true, $external_source, $external_id, (int) $existing['participant_count'] );
		}
		if ( $existing && 'fail_if_exists' === $idemp_mode ) {
			return new WP_Error( 'certpsu_issuance_exists', 'An issuance already exists for this external reference.' );
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		/**
		 * Participants list.
		 *
		 * @var array<int,array<string,mixed>> $participants
		 */
		$participants = $validated['participants'] ?? array();

		$issuance_id = $this->issuances->insert(
			array(
				'external_source'       => $external_source,
				'external_id'           => $external_id,
				'idempotency_key'       => $external_source . ':' . $external_id,
				'idempotency_mode'      => $idemp_mode,
				'status'                => 'queued',
				'current_step'          => 'queued',
				'auto_release'          => ( ! empty( $validated['auto_release'] ) ) ? 1 : 0,
				'certpsu_config_json'   => Json::encode( is_array( $validated['certpsu'] ?? null ) ? $validated['certpsu'] : array() ),
				'class_payload_json'    => Json::encode( is_array( $validated['class'] ?? null ) ? $validated['class'] : array() ),
				'template_payload_json' => Json::encode( is_array( $validated['certificate_template'] ?? null ) ? $validated['certificate_template'] : array() ),
				'participant_count'     => count( $participants ),
			)
		);

		if ( $issuance_id < 1 ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return new WP_Error( 'certpsu_db_insert_failed', 'Failed to insert issuance row.' );
		}

		$this->certificates->insert_many( $issuance_id, $participants );

		if ( ! $this->queue->enqueue_process_issuance( $issuance_id ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return new WP_Error( 'certpsu_queue_failed', 'Failed to enqueue issuance workflow.' );
		}

		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		do_action( 'certpsu_issuance_created', array( 'id' => $issuance_id ) );
		do_action( 'certpsu_issuance_queued', array( 'id' => $issuance_id ) );

		return new Issuance_Result( $issuance_id, 'queued', false, $external_source, $external_id, count( $participants ) );
	}
}
