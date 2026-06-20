<?php
/**
 * Admin notices for TutorLMS integration.
 *
 * @package CertPSU\TutorLMS
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS\Admin;

/**
 * Handles rendering of admin notices.
 */
final class Admin_Notices {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Render notices.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! isset( $_GET['certpsu_retroactive_queued'] ) ) {
			return;
		}

		$queued = (int) $_GET['certpsu_retroactive_queued'];

		if ( $queued > 0 ) {
			$message = sprintf(
				/* translators: %d: number of queued syncs */
				esc_html__( 'Success: %d past completion(s) queued for CertPSU synchronization. They will be processed in the background shortly.', 'certpsu-tutorlms' ),
				$queued
			);
			echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'No unsynced past completions were found for this course.', 'certpsu-tutorlms' ) . '</p></div>';
		}
	}
}
