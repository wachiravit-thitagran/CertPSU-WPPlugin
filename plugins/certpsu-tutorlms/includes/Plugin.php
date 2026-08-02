<?php
/**
 * Main plugin file for TutorLMS bridge.
 *
 * @package CertPSU\TutorLMS
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS;

use CertPSU\TutorLMS\Admin\Assets;
use CertPSU\TutorLMS\Admin\Course_Metabox;
use CertPSU\TutorLMS\Admin\Defaults_Page;
use CertPSU\TutorLMS\Integration\Tutor_Course_Builder;
use CertPSU\TutorLMS\Integration\My_Certificates_Integration;
use CertPSU\TutorLMS\Issuance\Completion_Handler;
use CertPSU\TutorLMS\Support\Github_Updater;

/**
 * Plugin core class.
 */
final class Plugin {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Course completion -> queue async job.
		( new Listener() )->register();

		// Async job: ensure class, add participant, release certificate on-the-fly.
		( new Completion_Handler() )->register();

		// Background sync: poll CertPSU API for release status and update certificate URLs.
		( new \CertPSU\TutorLMS\Issuance\Release_Sync_Handler() )->register();

		// Tutor LMS React course builder fields (save may run via REST, so this
		// is registered unconditionally).
		( new Tutor_Course_Builder() )->register();

		// Frontend user dashboard integration.
		( new My_Certificates_Integration() )->register();

		// Self-hosted updates. Registered outside is_admin() because the update
		// check also runs from cron.
		$this->init_updater();

		// Admin: per-course metabox, global defaults page, assets.
		if ( is_admin() ) {
			( new Course_Metabox() )->register();
			( new Defaults_Page() )->register();
			( new Assets() )->register();
			( new \CertPSU\TutorLMS\Admin\Retroactive_Sync() )->register();
			( new \CertPSU\TutorLMS\Admin\Admin_Notices() )->register();
		}
	}

	/**
	 * Register self-hosted updates from this repository's GitHub releases.
	 *
	 * The bridge ships from the same release as the connector but in its own
	 * `certpsu-tutorlms-<version>.zip` asset, and keeps its own updater so it
	 * stays updatable even when the connector is deactivated.
	 *
	 * @return void
	 */
	private function init_updater(): void {
		if ( ! defined( 'CERTPSU_TUTORLMS_FILE' ) ) {
			return;
		}

		/**
		 * Filters the repository a CertPSU plugin checks for releases.
		 *
		 * @param string $repo GitHub "owner/repo".
		 * @param string $slug Plugin slug asking for it.
		 */
		$repo = (string) apply_filters(
			'certpsu_github_repo',
			defined( 'CERTPSU_TUTORLMS_GITHUB_REPO' ) ? (string) CERTPSU_TUTORLMS_GITHUB_REPO : '',
			'certpsu-tutorlms'
		);

		$updater = new Github_Updater(
			(string) CERTPSU_TUTORLMS_FILE,
			'certpsu-tutorlms',
			$repo,
			defined( 'CERTPSU_TUTORLMS_VERSION' ) ? (string) CERTPSU_TUTORLMS_VERSION : ''
		);
		$updater->register();
	}
}
