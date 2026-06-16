<?php
/**
 * Migration 002.
 *
 * @package CertPSU\Connector\Database\Migrations
 */

declare(strict_types=1);

namespace CertPSU\Connector\Database\Migrations;

/**
 * Adds wp_user_id column to certpsu_certificates.
 */
final class Migration_002_Add_WP_User_ID {

	/**
	 * Run the migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$certificates = $wpdb->prefix . 'certpsu_certificates';
		
		$col_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$certificates} LIKE %s", 'wp_user_id' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( empty( $col_exists ) ) {
			$wpdb->query( "ALTER TABLE {$certificates} ADD COLUMN wp_user_id bigint unsigned DEFAULT NULL AFTER issuance_id" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( "ALTER TABLE {$certificates} ADD KEY wp_user_id (wp_user_id)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
	}
}
