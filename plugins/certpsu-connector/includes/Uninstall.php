<?php
/**
 * Uninstall helper.
 *
 * @package CertPSU\Connector
 */

declare(strict_types=1);

namespace CertPSU\Connector;

/**
 * Handles plugin uninstallation.
 */
final class Uninstall {

	/**
	 * Run on uninstall.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		$settings = get_option( 'certpsu_connector_settings', array() );
		if ( empty( $settings['delete_data_on_uninstall'] ) ) {
			return;
		}

		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}certpsu_issuances" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}certpsu_certificates" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}certpsu_api_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		delete_option( 'certpsu_connector_db_version' );
		delete_option( 'certpsu_connector_settings' );
	}
}
