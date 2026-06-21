<?php
/**
 * Background synchronization of class certificate release status.
 *
 * @package CertPSU\TutorLMS
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS\Issuance;

/**
 * Periodically polls CertPSU API for released certificates and updates user meta.
 */
final class Release_Sync_Handler {

	/**
	 * Recurring action hook.
	 */
	public const HOOK_ALL = 'certpsu_tutorlms_sync_all_classes';

	/**
	 * Single action hook for a specific class.
	 */
	public const HOOK_CLASS = 'certpsu_tutorlms_sync_class_users';

	/**
	 * Single action hook for a specific participant.
	 */
	public const HOOK_PARTICIPANT = 'certpsu_tutorlms_sync_participant_release';

	/**
	 * Async group.
	 */
	public const GROUP = 'certpsu-tutorlms';

	/**
	 * User meta key holding per-course issuance state.
	 */
	private const ISSUED_META = '_certpsu_tutorlms_issued';

	/**
	 * Register the async handlers.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'schedule_recurring' ) );
		add_action( self::HOOK_ALL, array( $this, 'sync_all_classes' ) );
		add_action( self::HOOK_CLASS, array( $this, 'sync_class_users' ), 10, 2 );
		add_action( self::HOOK_PARTICIPANT, array( $this, 'sync_participant_release' ), 10, 4 );
	}

	/**
	 * Schedule the recurring job if not already scheduled.
	 *
	 * @return void
	 */
	public function schedule_recurring(): void {
		if ( ! function_exists( 'as_next_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		if ( ! as_next_scheduled_action( self::HOOK_ALL ) ) {
			as_schedule_recurring_action( time(), HOUR_IN_SECONDS, self::HOOK_ALL, array(), self::GROUP );
		}
	}

	/**
	 * Sync all active CertPSU classes by enqueuing single jobs for each course.
	 *
	 * @return void
	 */
	public function sync_all_classes(): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}

		$query = new \WP_Query( array(
			'post_type'      => 'tutor_course',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => Course_Class_Manager::CLASS_ID_META,
					'compare' => 'EXISTS',
				),
			),
		) );

		$manager = new Course_Class_Manager();

		foreach ( $query->posts as $course_id ) {
			$course_id = (int) $course_id;
			$class_id  = $manager->class_id( $course_id );

			if ( '' !== $class_id ) {
				as_enqueue_async_action(
					self::HOOK_CLASS,
					array( $course_id, $class_id ),
					self::GROUP
				);
			}
		}
	}

	/**
	 * Find users in a class who need a certificate URL update and queue their sync.
	 *
	 * @param int    $course_id Course ID.
	 * @param string $class_id  CertPSU class id.
	 * @return void
	 */
	public function sync_class_users( int $course_id, string $class_id ): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}

		global $wpdb;
		$user_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT DISTINCT post_author
			FROM {$wpdb->posts}
			WHERE post_type = 'tutor_enrolled'
			AND post_parent = %d
		", $course_id ) );

		if ( empty( $user_ids ) ) {
			return;
		}

		foreach ( $user_ids as $uid ) {
			$user_id = (int) $uid;
			$state   = get_user_meta( $user_id, self::ISSUED_META, true );

			if ( ! is_array( $state ) || empty( $state[ $course_id ] ) ) {
				continue;
			}

			$course_state   = $state[ $course_id ];
			$participant_id = (string) ( $course_state['participant_id'] ?? '' );
			$cert_url       = (string) ( $course_state['certificate_url'] ?? '' );

			// Queue a check if they have a participant ID but no certificate URL.
			if ( '' !== $participant_id && '' === $cert_url ) {
				as_enqueue_async_action(
					self::HOOK_PARTICIPANT,
					array( $course_id, $class_id, $user_id, $participant_id ),
					self::GROUP
				);
			}
		}
	}

	/**
	 * Sync the release status for a specific participant.
	 *
	 * @param int    $course_id      Course ID.
	 * @param string $class_id       CertPSU class id.
	 * @param int    $user_id        WordPress user ID.
	 * @param string $participant_id CertPSU participant id.
	 * @return void
	 */
	public function sync_participant_release( int $course_id, string $class_id, int $user_id, string $participant_id ): void {
		if ( ! function_exists( 'certpsu' ) ) {
			return;
		}

		$client   = certpsu()->api();
		$response = $client->get_participant_release( $class_id, $participant_id );

		if ( ! $response->success ) {
			return;
		}

		$cert_url = (string) ( $response->data['certificate_url'] ?? '' );
		if ( '' !== $cert_url ) {
			$state = get_user_meta( $user_id, self::ISSUED_META, true );
			
			if ( is_array( $state ) && isset( $state[ $course_id ] ) ) {
				$state[ $course_id ]['released']        = true;
				$state[ $course_id ]['certificate_url'] = $cert_url;
				
				update_user_meta( $user_id, self::ISSUED_META, $state );
			}
		}
	}
}
