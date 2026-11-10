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

		$scope = 'course-class:' . $course_id;
		if ( ! \CertPSU\Connector\Support\Database_Claim::acquire( $scope ) ) {
			return new WP_Error( 'certpsu_class_locked', 'Class creation already in progress; will retry.' );
		}
		$journal_key = 'certpsu_class_checkpoint_' . $course_id;

		try {
			// Re-check fresh storage after acquiring the lock.
			wp_cache_delete( $course_id, 'post_meta' );
			wp_cache_delete( $journal_key, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			$existing = $this->class_id( $course_id );
			if ( '' !== $existing ) {
				return $existing;
			}

			// A durable pre-call marker prevents replay after timeout/crash. A saved
			// remote ID also repairs local postmeta failure without another create.
			$checkpoint = get_option( $journal_key, array() );
			if ( is_array( $checkpoint ) && ! empty( $checkpoint['class_id'] ) ) {
				$class_id = (string) $checkpoint['class_id'];
				update_post_meta( $course_id, self::CLASS_ID_META, $class_id );
				return $this->class_id( $course_id ) === $class_id ? $class_id : new WP_Error( 'certpsu_class_persist_failed', 'Saved remote class needs local persistence repair.' );
			}
			if ( ! empty( $checkpoint ) || ! add_option( $journal_key, array( 'outcome' => 'unknown' ), '', false ) ) {
				return new WP_Error( 'certpsu_class_outcome_unknown', 'Class creation outcome needs administrator reconciliation before retry.' );
			}
			$body     = $this->payload_builder->build( $course_id, $settings );
			$response = certpsu()->api()->create_class( $body );

			if ( ! $response->success ) {
				if ( $response->status_code >= 400 && $response->status_code < 500 && ! in_array( $response->status_code, array( 408, 409 ), true ) ) {
					delete_option( $journal_key );
				}
				return new WP_Error(
					$response->error_code ?: 'certpsu_create_class_failed',
					$response->error_message ?: 'Failed to create CertPSU class.'
				);
			}

			$class_id = (string) ( $response->data['id'] ?? '' );
			if ( '' === $class_id ) {
				return new WP_Error( 'certpsu_create_class_no_id', 'CertPSU did not return a class id.' );
			}

			update_option( $journal_key, array( 'class_id' => $class_id ), false );
			if ( get_option( $journal_key ) !== array( 'class_id' => $class_id ) ) {
				return new WP_Error( 'certpsu_class_checkpoint_failed', 'Remote class created but checkpoint could not be saved; reconcile before retry.' );
			}
			update_post_meta( $course_id, self::CLASS_ID_META, $class_id );
			if ( $this->class_id( $course_id ) !== $class_id ) {
				return new WP_Error( 'certpsu_class_persist_failed', 'Remote class checkpoint saved; retry to repair course metadata.' );
			}
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
			\CertPSU\Connector\Support\Database_Claim::release( $scope );
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
