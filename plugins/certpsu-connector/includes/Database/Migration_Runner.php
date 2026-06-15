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
		update_option( 'certpsu_connector_db_version', CERTPSU_CONNECTOR_DB_VERSION );
	}
}
