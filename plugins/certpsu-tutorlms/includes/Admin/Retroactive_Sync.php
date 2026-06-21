<?php
/**
 * Retroactive synchronization handler.
 *
 * @package CertPSU\TutorLMS
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS\Admin;

/**
 * Handles retroactive sync for a specific course via admin-post action.
 */
final class Retroactive_Sync {

	public const ACTION = 'certpsu_tutorlms_sync_retroactive';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Process the retroactive sync request.
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), self::ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'certpsu-tutorlms' ) );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'certpsu-tutorlms' ) );
		}

		$course_id = isset( $_GET['course_id'] ) ? (int) $_GET['course_id'] : 0;
		if ( $course_id < 1 ) {
			wp_die( esc_html__( 'Invalid course ID.', 'certpsu-tutorlms' ) );
		}

		if ( ! function_exists( 'tutor_utils' ) ) {
			wp_die( esc_html__( 'Tutor LMS is not active.', 'certpsu-tutorlms' ) );
		}

		$enrolled_users = tutor_utils()->get_enrolled_users_by_course( $course_id );
		$queued_count   = 0;

		if ( is_array( $enrolled_users ) ) {
			foreach ( $enrolled_users as $user ) {
				$user_id = (int) $user->ID;
				if ( tutor_utils()->is_completed_course( $course_id, $user_id ) ) {
					// Firing the tutor hook enqueues the action via our Listener
					do_action( 'tutor_course_complete_after', $course_id, $user_id );
					++$queued_count;
				}
			}
		}

		// Redirect back to the course edit screen with a success flag
		$redirect_url = add_query_arg(
			array(
				'post'                       => $course_id,
				'action'                     => 'edit',
				'certpsu_retroactive_queued' => $queued_count,
			),
			admin_url( 'post.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}
}
