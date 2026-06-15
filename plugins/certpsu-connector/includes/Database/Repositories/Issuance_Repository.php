<?php
/**
 * Issuance Repository.
 *
 * @package CertPSU\Connector\Database\Repositories
 */

declare(strict_types=1);

namespace CertPSU\Connector\Database\Repositories;

/**
 * Issuance repo.
 */
class Issuance_Repository {

	/**
	 * Insert issuance.
	 *
	 * @param array<string,mixed> $data Data.
	 * @return int
	 */
	public function insert( array $data ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'certpsu_issuances';
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table,
			wp_parse_args(
				$data,
				array(
					'failed_step'        => null,
					'certpsu_class_id'   => null,
					'ready_count'        => 0,
					'failed_count'       => 0,
					'attempt_count'      => 0,
					'poll_attempt_count' => 0,
					'created_at'         => current_time( 'mysql', true ),
					'updated_at'         => current_time( 'mysql', true ),
				)
			)
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Find latest by external reference.
	 *
	 * @param string $source Source.
	 * @param string $external_id ID.
	 * @return array<string,mixed>|null
	 */
	public function find_latest_by_external_ref( string $source, string $external_id ): ?array {
		global $wpdb;

		$result = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}certpsu_issuances WHERE external_source = %s AND external_id = %s ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$source,
				$external_id
			),
			'ARRAY_A'
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return is_array( $result ) ? $result : null;
	}

	/**
	 * Find by ID.
	 *
	 * @param int $id ID.
	 * @return array<string,mixed>|null
	 */
	public function find_by_id( int $id ): ?array {
		global $wpdb;

		$result = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}certpsu_issuances WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			),
			'ARRAY_A'
		);

		return is_array( $result ) ? $result : null;
	}

	/**
	 * Mark failed.
	 *
	 * @param int         $id ID.
	 * @param string      $step Step.
	 * @param string|null $code Code.
	 * @param string|null $message Message.
	 * @return void
	 */
	public function mark_failed( int $id, string $step, ?string $code, ?string $message ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'certpsu_issuances',
			array(
				'status'             => 'failed',
				'failed_step'        => $step,
				'last_error_code'    => $code,
				'last_error_message' => $message,
				'updated_at'         => current_time( 'mysql', true ),
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Mark class created.
	 *
	 * @param int    $id ID.
	 * @param string $class_id Class ID.
	 * @return void
	 */
	public function mark_class_created( int $id, string $class_id ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'certpsu_issuances',
			array(
				'current_step'     => 'create_class',
				'certpsu_class_id' => $class_id,
				'updated_at'       => current_time( 'mysql', true ),
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Mark participants added.
	 *
	 * @param int $id ID.
	 * @return void
	 */
	public function mark_participants_added( int $id ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'certpsu_issuances',
			array(
				'current_step' => 'add_participants',
				'updated_at'   => current_time( 'mysql', true ),
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Mark waiting for release.
	 *
	 * @param int $id ID.
	 * @return void
	 */
	public function mark_waiting_for_release( int $id ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'certpsu_issuances',
			array(
				'status'       => 'waiting_for_release',
				'current_step' => 'waiting_for_release',
				'updated_at'   => current_time( 'mysql', true ),
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Mark released.
	 *
	 * @param int $id ID.
	 * @return void
	 */
	public function mark_released( int $id ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'certpsu_issuances',
			array(
				'status'       => 'released',
				'current_step' => 'release',
				'released_at'  => current_time( 'mysql', true ),
				'updated_at'   => current_time( 'mysql', true ),
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Mark completed.
	 *
	 * @param int $id ID.
	 * @return void
	 */
	public function mark_completed( int $id ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'certpsu_issuances',
			array(
				'status'       => 'completed',
				'current_step' => 'completed',
				'completed_at' => current_time( 'mysql', true ),
				'updated_at'   => current_time( 'mysql', true ),
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Mark terminal poll state.
	 *
	 * @param int    $id ID.
	 * @param string $status Status.
	 * @return void
	 */
	public function mark_terminal_poll_state( int $id, string $status ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'certpsu_issuances',
			array(
				'status'       => $status,
				'current_step' => 'poll_urls',
				'updated_at'   => current_time( 'mysql', true ),
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Increment poll attempts.
	 *
	 * @param int $id ID.
	 * @return void
	 */
	public function increment_poll_attempts( int $id ): void {
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}certpsu_issuances SET poll_attempt_count = poll_attempt_count + 1, updated_at = %s WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'mysql', true ),
				$id
			)
		);
	}
}
