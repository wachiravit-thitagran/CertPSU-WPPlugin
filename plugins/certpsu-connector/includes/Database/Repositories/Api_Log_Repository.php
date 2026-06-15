<?php
/**
 * API Log Repository.
 *
 * @package CertPSU\Connector\Database\Repositories
 */

declare(strict_types=1);

namespace CertPSU\Connector\Database\Repositories;

/**
 * API Log repository.
 */
final class Api_Log_Repository {

	/**
	 * Insert log.
	 *
	 * @param array<string,mixed> $data Data.
	 * @return int
	 */
	public function insert( array $data ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'certpsu_api_logs';
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table,
			wp_parse_args(
				$data,
				array(
					'created_at' => current_time( 'mysql', true ),
				)
			)
		);

		return (int) $wpdb->insert_id;
	}
}
