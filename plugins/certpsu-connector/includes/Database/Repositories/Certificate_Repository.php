<?php
/**
 * Certificate Repository.
 *
 * @package CertPSU\Connector\Database\Repositories
 */

declare(strict_types=1);

namespace CertPSU\Connector\Database\Repositories;

use CertPSU\Connector\Support\Json;

/**
 * Certificate repository.
 */
final class Certificate_Repository {

	/**
	 * Insert many certificates.
	 *
	 * @param int                            $issuance_id Issuance ID.
	 * @param array<int,array<string,mixed>> $participants Participants.
	 * @return void
	 */
	public function insert_many( int $issuance_id, array $participants ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'certpsu_certificates';
		foreach ( $participants as $participant ) {
			$email     = isset( $participant['email'] ) ? (string) $participant['email'] : '';
			$common_id = isset( $participant['common_id'] ) ? (string) $participant['common_id'] : '';
			$name      = isset( $participant['name'] ) ? (string) $participant['name'] : '';

			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$table,
				array(
					'issuance_id'      => $issuance_id,
					'external_id'      => $participant['external_id'] ?? null,
					'participant_hash' => hash( 'sha256', strtolower( $email . '|' . $common_id . '|' . $name ) ),
					'name'             => $participant['name'],
					'email'            => $participant['email'],
					'common_id'        => $participant['common_id'] ?? null,
					'organization'     => $participant['organization'] ?? null,
					'group_name'       => $participant['group'],
					'extra_json'       => Json::encode( $participant['extra'] ?? array() ),
					'status'           => 'pending',
					'created_at'       => current_time( 'mysql', true ),
					'updated_at'       => current_time( 'mysql', true ),
				)
			);
		}
	}

	/**
	 * Build payload.
	 *
	 * @param int $issuance_id ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function build_participants_payload( int $issuance_id ): array {
		global $wpdb;
		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT participant_hash, name, email, common_id, group_name, organization, extra_json FROM {$wpdb->prefix}certpsu_certificates WHERE issuance_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$issuance_id
			),
			'ARRAY_A'
		);
		$payload = array();
		if ( ! is_array( $results ) ) {
			return $payload;
		}
		foreach ( $results as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			// API v2 assigns participant ids server-side, so we no longer send our
			// own `id`. Rows are reconciled back by email during URL sync.
			$payload[] = array(
				'name'         => $row['name'],
				'email'        => $row['email'],
				'common_id'    => $row['common_id'],
				'group'        => $row['group_name'],
				'organization' => $row['organization'],
				'extra'        => Json::decode( (string) $row['extra_json'] ),
			);
		}
		return $payload;
	}

	/**
	 * Mark all for issuance.
	 *
	 * @param int    $issuance_id ID.
	 * @param string $status Status.
	 * @return void
	 */
	public function mark_all_for_issuance( int $issuance_id, string $status ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'certpsu_certificates',
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'issuance_id' => $issuance_id )
		);
	}

	/**
	 * Sync URLs from CertPSU.
	 *
	 * @param int                            $issuance_id Issuance ID.
	 * @param array<int,array<string,mixed>> $participants API Participants.
	 * @return array{ready_count:int,participant_count:int}
	 */
	public function sync_urls_from_certpsu( int $issuance_id, array $participants ): array {
		global $wpdb;

		$ready_count = 0;
		$total       = 0;

		$table = $wpdb->prefix . 'certpsu_certificates';

		foreach ( $participants as $participant ) {
			// Reconcile by email: it is unique within an issuance (the validator
			// rejects duplicate participant emails before enqueue) and is the only
			// stable key we share with API v2, which generates participant ids itself.
			$email           = isset( $participant['email'] ) ? (string) $participant['email'] : '';
			$participant_id  = $participant['id'] ?? null;
			$cert            = $participant['certificate_id'] ?? null;
			$url             = $participant['certificate_url'] ?? null;

			if ( '' === $email ) {
				continue;
			}

			++$total;

			if ( $cert && $url ) {
				$data = array(
					'certpsu_certificate_id' => $cert,
					'certificate_url'        => $url,
					'status'                 => 'ready',
					'ready_at'               => current_time( 'mysql', true ),
					'updated_at'             => current_time( 'mysql', true ),
				);
				if ( $participant_id ) {
					$data['certpsu_participant_id'] = $participant_id;
				}

				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$table,
					$data,
					array(
						'issuance_id' => $issuance_id,
						'email'       => $email,
					)
				);
				++$ready_count;
			}
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'certpsu_issuances',
			array(
				'ready_count' => $ready_count,
				'updated_at'  => current_time( 'mysql', true ),
			),
			array( 'id' => $issuance_id )
		);

		return array(
			'ready_count'       => $ready_count,
			'participant_count' => $total,
		);
	}
}
