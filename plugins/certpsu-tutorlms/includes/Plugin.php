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
use CertPSU\TutorLMS\Issuance\Completion_Handler;

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

		// Tutor LMS React course builder fields (save may run via REST, so this
		// is registered unconditionally).
		( new Tutor_Course_Builder() )->register();

		// Admin: per-course metabox, global defaults page, assets.
		if ( is_admin() ) {
			( new Course_Metabox() )->register();
			( new Defaults_Page() )->register();
			( new Assets() )->register();
		}
	}
}
