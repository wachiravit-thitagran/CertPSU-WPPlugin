<?php
/**
 * Action Scheduler Loader.
 *
 * @package CertPSU\Connector\Queue
 */

declare(strict_types=1);

namespace CertPSU\Connector\Queue;

/**
 * Loads Action Scheduler.
 */
final class Action_Scheduler_Loader {

	/**
	 * Load AS.
	 *
	 * @return void
	 */
	public function load(): void {
		if ( class_exists( 'ActionScheduler' ) ) {
			return;
		}

		$relative = '/vendor/woocommerce/action-scheduler/action-scheduler.php';

		// Candidate paths in priority order:
		// 1. Plugin-local vendor — bundled inside the distributed zip (standalone install).
		// 2. Monorepo-root vendor — shared during local development.
		$candidates = array(
			rtrim( CERTPSU_CONNECTOR_PATH, '/' ) . $relative,
			dirname( __DIR__, 4 ) . $relative,
		);

		foreach ( $candidates as $path ) {
			if ( file_exists( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
}
