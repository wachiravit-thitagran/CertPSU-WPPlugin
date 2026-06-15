<?php
/**
 * Listener for TutorLMS hooks.
 *
 * @package CertPSU\TutorLMS
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS;

use CertPSU\TutorLMS\Issuance\Completion_Handler;
use CertPSU\TutorLMS\Settings\Course_Settings;

/**
 * Listens for course completions and queues certificate issuance.
 */
final class Listener {

	/**
	 * Register WordPress actions.
	 *
	 * @return void
	 */
	public function register(): void {
		// Tutor LMS fires: do_action( 'tutor_course_complete_after', $course_id, $user_id ).
		add_action( 'tutor_course_complete_after', array( $this, 'on_course_completed' ), 10, 2 );
	}

	/**
	 * Handle course completion event.
	 *
	 * @param mixed $course_id The completed course ID.
	 * @param mixed $user_id   The user ID (older Tutor versions omit this).
	 * @return void
	 */
	public function on_course_completed( mixed $course_id, mixed $user_id = 0 ): void {
		$course_id = (int) $course_id;
		$user_id   = (int) $user_id;
		if ( $user_id < 1 ) {
			$user_id = get_current_user_id();
		}

		if ( $course_id < 1 || $user_id < 1 ) {
			return;
		}

		// Skip quickly if issuance is not enabled for this course.
		$settings = Course_Settings::for_course( $course_id );
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}

		as_enqueue_async_action(
			Completion_Handler::HOOK,
			array(
				array(
					'course_id' => $course_id,
					'user_id'   => $user_id,
					'attempt'   => 1,
				),
			),
			Completion_Handler::GROUP
		);
	}
}
