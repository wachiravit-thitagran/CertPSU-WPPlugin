<?php
/**
 * Migration 001.
 *
 * @package CertPSU\Connector\Database\Migrations
 */

declare(strict_types=1);

namespace CertPSU\Connector\Database\Migrations;

/**
 * Creates core tables.
 */
final class Migration_001_Create_Core_Tables {

	/**
	 * Run the migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset      = $wpdb->get_charset_collate();
		$issuances    = $wpdb->prefix . 'certpsu_issuances';
		$certificates = $wpdb->prefix . 'certpsu_certificates';
		$logs         = $wpdb->prefix . 'certpsu_api_logs';

		dbDelta(
			"CREATE TABLE {$issuances} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            external_source varchar(100) NOT NULL,
            external_id varchar(191) NOT NULL,
            idempotency_key varchar(255) NOT NULL,
            idempotency_mode varchar(30) NOT NULL,
            status varchar(40) NOT NULL,
            current_step varchar(40) DEFAULT NULL,
            failed_step varchar(40) DEFAULT NULL,
            auto_release tinyint(1) NOT NULL DEFAULT 1,
            certpsu_class_id varchar(191) DEFAULT NULL,
            certpsu_config_json longtext NOT NULL,
            class_payload_json longtext NOT NULL,
            template_payload_json longtext NOT NULL,
            participant_count int unsigned NOT NULL DEFAULT 0,
            ready_count int unsigned NOT NULL DEFAULT 0,
            failed_count int unsigned NOT NULL DEFAULT 0,
            attempt_count int unsigned NOT NULL DEFAULT 0,
            poll_attempt_count int unsigned NOT NULL DEFAULT 0,
            last_error_code varchar(100) DEFAULT NULL,
            last_error_message text DEFAULT NULL,
            last_attempt_at datetime DEFAULT NULL,
            next_retry_at datetime DEFAULT NULL,
            created_by bigint unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            completed_at datetime DEFAULT NULL,
            released_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY external_ref (external_source, external_id),
            KEY status (status),
            KEY current_step (current_step),
            KEY certpsu_class_id (certpsu_class_id)
        ) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$certificates} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            issuance_id bigint unsigned NOT NULL,
            external_id varchar(191) DEFAULT NULL,
            participant_hash varchar(64) NOT NULL,
            name varchar(255) NOT NULL,
            email varchar(320) NOT NULL,
            common_id varchar(191) DEFAULT NULL,
            organization varchar(255) DEFAULT NULL,
            group_name varchar(60) NOT NULL,
            extra_json longtext DEFAULT NULL,
            status varchar(40) NOT NULL,
            certpsu_participant_id varchar(191) DEFAULT NULL,
            certpsu_certificate_id varchar(191) DEFAULT NULL,
            certificate_url text DEFAULT NULL,
            certificate_check_url text DEFAULT NULL,
            attempt_count int unsigned NOT NULL DEFAULT 0,
            last_error_code varchar(100) DEFAULT NULL,
            last_error_message text DEFAULT NULL,
            last_attempt_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            ready_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY issuance_status (issuance_id, status),
            KEY certpsu_certificate_id (certpsu_certificate_id)
        ) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$logs} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            issuance_id bigint unsigned DEFAULT NULL,
            certificate_id bigint unsigned DEFAULT NULL,
            method varchar(10) NOT NULL,
            endpoint varchar(255) NOT NULL,
            request_query_json longtext DEFAULT NULL,
            request_body_json longtext DEFAULT NULL,
            response_status int DEFAULT NULL,
            response_body_json longtext DEFAULT NULL,
            success tinyint(1) NOT NULL DEFAULT 0,
            error_code varchar(100) DEFAULT NULL,
            error_message text DEFAULT NULL,
            duration_ms int unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY issuance_id (issuance_id),
            KEY endpoint (endpoint(191))
        ) {$charset};"
		);
	}
}
