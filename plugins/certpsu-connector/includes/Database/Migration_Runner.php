<?php
/**
 * Migration Runner.
 *
 * @package CertPSU\Connector\Database
 */

declare(strict_types=1);

namespace CertPSU\Connector\Database;

/**
 * Migration runner.
 */
final class Migration_Runner {

	/**
	 * Run migrations.
	 *
	 * @return void
	 */
	public function migrate(): void {
		( new Migrations\Migration_001_Create_Core_Tables() )->up();
		( new Migrations\Migration_002_Add_WP_User_ID() )->up();
		update_option( 'certpsu_connector_db_version', CERTPSU_CONNECTOR_DB_VERSION );
	}
}
