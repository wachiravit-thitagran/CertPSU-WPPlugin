<?php
/**
 * Test for Migrations.
 *
 * @package CertPSU\Connector\Tests\Integration\Database
 */

declare(strict_types=1);

namespace CertPSU\Connector\Tests\Integration\Database;

use PHPUnit\Framework\TestCase;

/**
 * Tests database migration during activation.
 */
final class MigrationTest extends TestCase {

	/**
	 * Ensure activation creates tables.
	 *
	 * @return void
	 */
	public function test_activation_creates_core_tables(): void {
		if ( ! function_exists( 'dbDelta' ) ) {
			self::markTestSkipped( 'Database tests require WordPress environment.' );
		}

		global $wpdb;

		\CertPSU\Connector\Activation::activate( false );

		self::assertSame(
			$wpdb->prefix . 'certpsu_issuances',
			$wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}certpsu_issuances'" ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		);
		self::assertSame(
			'1',
			(string) get_option( 'certpsu_connector_db_version' )
		);
	}
}
