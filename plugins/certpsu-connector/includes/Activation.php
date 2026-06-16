<?php
/**
 * Activation class.
 *
 * @package CertPSU\Connector
 */

declare(strict_types=1);

namespace CertPSU\Connector;

use CertPSU\Connector\Database\Migration_Runner;
use CertPSU\Connector\Database\Page_Installer;

/**
 * Handles plugin activation.
 */
final class Activation {

	/**
	 * Run on activation.
	 *
	 * @param bool $network_wide Whether activation is network wide.
	 *
	 * @return void
	 */
	public static function activate( bool $network_wide ): void {
		if ( is_multisite() && $network_wide ) {
			/**
			 * List of site IDs.
			 *
			 * @var array<string> $site_ids
			 */
			$site_ids = get_sites( array( 'fields' => 'ids' ) );
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				( new Migration_Runner() )->migrate();
				( new Page_Installer() )->install();
				restore_current_blog();
			}
			return;
		}

		( new Migration_Runner() )->migrate();
		( new Page_Installer() )->install();
	}
}
