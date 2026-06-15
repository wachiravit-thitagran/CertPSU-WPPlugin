<?php
/**
 * Admin asset loader.
 *
 * @package CertPSU\TutorLMS
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS\Admin;

use CertPSU\TutorLMS\Settings\Course_Settings;

/**
 * Enqueues the settings CSS/JS on the course editor and defaults page.
 */
final class Assets {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue assets where needed.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( ! $this->should_load( $hook ) ) {
			return;
		}

		$base = defined( 'CERTPSU_TUTORLMS_URL' ) ? CERTPSU_TUTORLMS_URL : '';
		$ver  = defined( 'CERTPSU_TUTORLMS_VERSION' ) ? CERTPSU_TUTORLMS_VERSION : false;

		wp_enqueue_style( 'certpsu-tutorlms-admin', $base . 'assets/admin.css', array(), $ver );
		wp_enqueue_script( 'certpsu-tutorlms-admin', $base . 'assets/admin.js', array(), $ver, true );
	}

	/**
	 * Whether the current screen should load the assets.
	 *
	 * @param string $hook Hook suffix.
	 * @return bool
	 */
	private function should_load( string $hook ): bool {
		if ( 'settings_page_certpsu-tutorlms-defaults' === $hook ) {
			return true;
		}

		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			return $screen && Course_Settings::COURSE_POST_TYPE === $screen->post_type;
		}

		return false;
	}
}
