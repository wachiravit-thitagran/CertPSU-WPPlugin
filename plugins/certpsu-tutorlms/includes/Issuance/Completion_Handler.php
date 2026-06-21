<?php
/**
 * Handles the asynchronous course-completion -> certificate flow.
 *
 * @package CertPSU\TutorLMS
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS\Issuance;

use CertPSU\TutorLMS\Settings\Course_Settings;
use CertPSU\TutorLMS\Support\Learner;
use WP_Error;

/**
 * On completion: ensure the course class exists, add the learner as a
 * participant, and release that learner's certificate on-the-fly.
 */
final class Completion_Handler {

	/**
	 * Action Scheduler hook this handler is bound to.
	 */
	public const HOOK = 'certpsu_tutorlms_issue';

	/**
	 * Async group.
	 */
	public const GROUP = 'certpsu-tutorlms';

	/**
	 * Maximum number of attempts before giving up.
	 */
	private const MAX_ATTEMPTS = 5;

	/**
	 * User meta key holding per-course issuance state.
	 */
	private const ISSUED_META = '_certpsu_tutorlms_issued';

	/**
	 * Constructor.
	 *
	 * @param Course_Class_Manager $classes Class manager.
	 * @param Learner              $learner Learner resolver.
	 */
	public function __construct(
		private Course_Class_Manager $classes = new Course_Class_Manager(),
		private Learner $learner = new Learner()
	) {}

	/**
	 * Register the async handler.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( self::HOOK, array( $this, 'handle' ), 10, 1 );
	}

	/**
	 * Handle one completion job.
	 *
	 * @param array<string,mixed> $args { course_id, user_id, attempt }.
	 * @return void
	 */
	public function handle( array $args = array() ): void {
		$course_id = isset( $args['course_id'] ) ? (int) $args['course_id'] : 0;
		$user_id   = isset( $args['user_id'] ) ? (int) $args['user_id'] : 0;
		$attempt   = isset( $args['attempt'] ) ? (int) $args['attempt'] : 1;

		if ( $course_id < 1 || $user_id < 1 ) {
			return;
		}

		$settings = Course_Settings::for_course( $course_id );
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		// Idempotency: never issue twice for the same learner + course.
		if ( $this->already_released( $user_id, $course_id ) ) {
			return;
		}

		$participant = $this->learner->participant( $user_id, $course_id, $settings );
		if ( null === $participant ) {
			$this->log( $course_id, $user_id, 'skipped: learner has no usable email' );
			return;
		}

		// 1) Ensure the course has a CertPSU class.
		$class_id = $this->classes->ensure_class( $course_id, $settings );
		if ( $class_id instanceof WP_Error ) {
			$this->maybe_retry( $course_id, $user_id, $attempt, $class_id );
			return;
		}

		if ( ! function_exists( 'certpsu' ) ) {
			$this->maybe_retry( $course_id, $user_id, $attempt, new WP_Error( 'certpsu_connector_inactive', 'Connector inactive.' ) );
			return;
		}

		$client = certpsu()->api();

		// 2) Add the learner as a participant. API v2 always returns the participant
		// with its server-assigned id (for both new and existing participants), so
		// a successful add is enough to obtain the id — no lookup needed. A failed
		// add is retried as a whole.
		$add = $client->add_participants( $class_id, array( 'participants' => array( $participant ) ) );
		if ( ! $add->success ) {
			$this->log( $course_id, $user_id, 'add_participant failed: ' . (string) $add->error_message );
			$this->maybe_retry( $course_id, $user_id, $attempt, new WP_Error( (string) ( $add->error_code ?: 'certpsu_add_failed' ), (string) $add->error_message ) );
			return;
		}

		/**
		 * Fires after a learner is added to a course's class.
		 *
		 * @param int    $course_id Course ID.
		 * @param int    $user_id   User ID.
		 * @param string $class_id  CertPSU class id.
		 */
		do_action( 'certpsu_tutorlms_participant_added', $course_id, $user_id, $class_id );

		$participant_id = $this->extract_participant_id( $add->data );
		if ( '' === $participant_id ) {
			$this->maybe_retry( $course_id, $user_id, $attempt, new WP_Error( 'certpsu_no_participant_id', 'add_participants returned no participant id.' ) );
			return;
		}

		// 3) Release this learner's certificate on-the-fly (unless auto-release is off).
		if ( empty( $settings['auto_release'] ) ) {
			$this->remember( $user_id, $course_id, $class_id, $participant_id, false, '' );
			return;
		}

		$release = $client->release_participant( $class_id, $participant_id );
		if ( ! $release->success ) {
			$this->log( $course_id, $user_id, 'release failed: ' . (string) $release->error_message );
			$this->maybe_retry( $course_id, $user_id, $attempt, new WP_Error( (string) $release->error_code, (string) $release->error_message ) );
			return;
		}

		$certificate_url = isset( $release->data['certificate_url'] ) ? (string) $release->data['certificate_url'] : '';
		$this->remember( $user_id, $course_id, $class_id, $participant_id, true, $certificate_url );

		/**
		 * Fires after a learner's certificate is released.
		 *
		 * @param int    $course_id      Course ID.
		 * @param int    $user_id        User ID.
		 * @param string $class_id       CertPSU class id.
		 * @param string $participant_id CertPSU participant id.
		 */
		do_action( 'certpsu_tutorlms_certificate_released', $course_id, $user_id, $class_id, $participant_id );
	}

	/**
	 * Extract the participant id from an add_participants response.
	 *
	 * We send exactly one participant, and v2 returns the created participants
	 * directly in the unwrapped `data` array (or in `participants` if v1/compat).
	 *
	 * @param array<string,mixed>|array<int,mixed>|null $data Unwrapped response data.
	 * @return string Participant id, or '' if absent.
	 */
	private function extract_participant_id( ?array $data ): string {
		$participants = is_array( $data ) && isset( $data['participants'] ) && is_array( $data['participants'] )
			? $data['participants']
			: ( is_array( $data ) ? $data : array() );

		foreach ( $participants as $p ) {
			if ( is_array( $p ) && ! empty( $p['id'] ) ) {
				return (string) $p['id'];
			}
		}

		return '';
	}

	/**
	 * Whether a certificate has already been released for this learner + course.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	private function already_released( int $user_id, int $course_id ): bool {
		$state = $this->issued_state( $user_id );
		return ! empty( $state[ $course_id ]['released'] );
	}

	/**
	 * Persist issuance state for a learner + course.
	 *
	 * @param int    $user_id         User ID.
	 * @param int    $course_id       Course ID.
	 * @param string $class_id        Class id.
	 * @param string $participant_id  CertPSU participant id.
	 * @param bool   $released        Whether the certificate was released.
	 * @param string $certificate_url Certificate URL.
	 * @return void
	 */
	private function remember( int $user_id, int $course_id, string $class_id, string $participant_id, bool $released, string $certificate_url = '' ): void {
		$state               = $this->issued_state( $user_id );
		$state[ $course_id ] = array(
			'class_id'        => $class_id,
			'participant_id'  => $participant_id,
			'released'        => $released,
			'certificate_url' => $certificate_url,
			'at'              => gmdate( 'Y-m-d H:i:s' ),
		);
		update_user_meta( $user_id, self::ISSUED_META, $state );
	}

	/**
	 * Read issuance state map for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array<int,array<string,mixed>>
	 */
	private function issued_state( int $user_id ): array {
		$state = get_user_meta( $user_id, self::ISSUED_META, true );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Re-enqueue the job on a retryable failure, up to MAX_ATTEMPTS.
	 *
	 * @param int      $course_id Course ID.
	 * @param int      $user_id   User ID.
	 * @param int      $attempt   Current attempt.
	 * @param WP_Error $error     Error.
	 * @return void
	 */
	private function maybe_retry( int $course_id, int $user_id, int $attempt, WP_Error $error ): void {
		$this->log( $course_id, $user_id, 'attempt ' . $attempt . ' failed: ' . $error->get_error_message() );

		if ( $attempt >= self::MAX_ATTEMPTS || ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		as_schedule_single_action(
			time() + ( 2 * MINUTE_IN_SECONDS ),
			self::HOOK,
			array(
				array(
					'course_id' => $course_id,
					'user_id'   => $user_id,
					'attempt'   => $attempt + 1,
				),
			),
			self::GROUP
		);
	}

	/**
	 * Lightweight error log.
	 *
	 * @param int    $course_id Course ID.
	 * @param int    $user_id   User ID.
	 * @param string $message   Message.
	 * @return void
	 */
	private function log( int $course_id, int $user_id, string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				sprintf( '[certpsu-tutorlms] course %d, user %d: %s', $course_id, $user_id, $message )
			);
		}
	}
}
