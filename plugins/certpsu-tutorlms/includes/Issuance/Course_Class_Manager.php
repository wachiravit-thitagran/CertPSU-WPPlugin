<?php
/**
 * Manages the one-class-per-course lifecycle.
 *
 * @package CertPSU\TutorLMS
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS\Issuance;

use CertPSU\TutorLMS\Settings\Course_Settings;
use WP_Error;

/**
 * Lazily creates a single CertPSU class for a course and stores its id, so that
 * learners can be added as participants on completion.
 */
final class Course_Class_Manager {

	/**
	 * Post meta key holding the CertPSU class id for a course.
	 */
	public const CLASS_ID_META = '_certpsu_class_id';

	/**
	 * Post meta key holding when the class was created.
	 */
	public const CREATED_AT_META = '_certpsu_class_created_at';

	/**
	 * Constructor.
	 *
	 * @param Class_Payload_Builder $payload_builder Payload builder.
	 */
	public function __construct(
		private Class_Payload_Builder $payload_builder = new Class_Payload_Builder()
	) {}

	/**
	 * The stored class id for a course, or empty string.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public function class_id( int $course_id ): string {
		return (string) get_post_meta( $course_id, self::CLASS_ID_META, true );
	}

	/**
	 * Ensure a CertPSU class exists for the course, creating it on first use.
	 *
	 * @param int                 $course_id Course ID.
	 * @param array<string,mixed> $settings  Effective course settings.
	 * @return string|WP_Error Class id, or error.
	 */
	public function ensure_class( int $course_id, array $settings ): string|WP_Error {
		$existing = $this->class_id( $course_id );
		if ( '' !== $existing ) {
			return $existing;
		}

		if ( ! function_exists( 'certpsu' ) ) {
			return new WP_Error( 'certpsu_connector_inactive', 'CertPSU Connector is not active.' );
		}

		// Guard against two completions creating the class concurrently.
		$lock_key = 'certpsu_class_lock_' . $course_id;
		if ( false !== get_transient( $lock_key ) ) {
			return new WP_Error( 'certpsu_class_locked', 'Class creation already in progress; will retry.' );
		}
		set_transient( $lock_key, 1, 2 * MINUTE_IN_SECONDS );

		try {
			// Re-check after acquiring the lock.
			$existing = $this->class_id( $course_id );
			if ( '' !== $existing ) {
				return $existing;
			}

			$body     = $this->payload_builder->build( $course_id, $settings );
			$response = certpsu()->api()->create_class( $body );

			if ( ! $response->success ) {
				return new WP_Error(
					$response->error_code ?: 'certpsu_create_class_failed',
					$response->error_message ?: 'Failed to create CertPSU class.'
				);
			}

			$class_id = (string) ( $response->data['id'] ?? '' );
			if ( '' === $class_id ) {
				return new WP_Error( 'certpsu_create_class_no_id', 'CertPSU did not return a class id.' );
			}

			update_post_meta( $course_id, self::CLASS_ID_META, $class_id );
			update_post_meta( $course_id, self::CREATED_AT_META, gmdate( 'Y-m-d H:i:s' ) );

			/**
			 * Fires after a course's CertPSU class is created.
			 *
			 * @param int    $course_id Course ID.
			 * @param string $class_id  CertPSU class id.
			 */
			do_action( 'certpsu_tutorlms_class_created', $course_id, $class_id );

			return $class_id;
		} finally {
			delete_transient( $lock_key );
		}
	}

	/**
	 * Effective settings for a course (helper passthrough).
	 *
	 * @param int $course_id Course ID.
	 * @return array<string,mixed>
	 */
	public function settings( int $course_id ): array {
		return Course_Settings::for_course( $course_id );
	}
}
