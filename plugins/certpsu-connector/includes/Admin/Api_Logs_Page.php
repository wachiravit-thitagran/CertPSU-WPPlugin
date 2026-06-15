<?php
/**
 * API Logs Page.
 *
 * @package CertPSU\Connector\Admin
 */

declare(strict_types=1);

namespace CertPSU\Connector\Admin;

/**
 * API Logs page.
 */
final class Api_Logs_Page {

	/**
	 * Render.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		echo '<div class="wrap"><h1>API Logs</h1></div>';
	}
}
