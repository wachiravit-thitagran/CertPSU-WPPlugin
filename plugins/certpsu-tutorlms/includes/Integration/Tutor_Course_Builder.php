<?php
/**
 * Tutor LMS course-builder field integration.
 *
 * @package CertPSU\TutorLMS
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS\Integration;

use CertPSU\TutorLMS\Settings\Course_Settings;

/**
 * Surfaces a curated set of CertPSU per-course settings inside the Tutor LMS
 * (3.x) React course builder, alongside the full WP-admin metabox.
 *
 * The builder fields write to the same `_certpsu_course_settings` post meta as
 * the metabox, so both stay in sync; advanced fields (endorsers, class dates,
 * etc.) remain in the metabox / global defaults.
 */
final class Tutor_Course_Builder {

	/**
	 * Builder field name => settings key. The `bool` flag marks switch fields.
	 *
	 * @var array<string,array{key:string,bool:bool}>
	 */
	private const FIELD_MAP = array(
		'certpsu_enabled'                    => array( 'key' => 'enabled', 'bool' => true ),
		'certpsu_template_id'                => array( 'key' => 'template_id', 'bool' => false ),
		'certpsu_template_group'             => array( 'key' => 'template_group', 'bool' => false ),
		'certpsu_template_name'              => array( 'key' => 'template_name', 'bool' => false ),
		'certpsu_certificate_text'           => array( 'key' => 'certificate_text', 'bool' => false ),
		'certpsu_auto_release'               => array( 'key' => 'auto_release', 'bool' => true ),
		'certpsu_organization_id'            => array( 'key' => 'organization_id', 'bool' => false ),
		'certpsu_certificate_email_template' => array( 'key' => 'certificate_email_template', 'bool' => false ),
	);

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'tutor_after_course_builder_load', array( $this, 'enqueue' ) );
		add_filter( 'tutor_course_details_response', array( $this, 'prefill' ) );
		add_action( 'save_post_' . Course_Settings::COURSE_POST_TYPE, array( $this, 'save' ), 10, 1 );
	}

	/**
	 * Enqueue the field-registration script for the React course builder.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! defined( 'CERTPSU_TUTORLMS_URL' ) ) {
			return;
		}

		$ver = defined( 'CERTPSU_TUTORLMS_VERSION' ) ? CERTPSU_TUTORLMS_VERSION : false;

		wp_enqueue_script(
			'certpsu-tutorlms-course-builder',
			CERTPSU_TUTORLMS_URL . 'assets/course-builder.js',
			array( 'tutor-course-builder' ),
			$ver,
			true
		);

		$groups = array();
		foreach ( Course_Settings::groups() as $value => $label ) {
			$groups[] = array(
				'value' => (string) $value,
				'label' => (string) $label,
			);
		}

		wp_localize_script(
			'certpsu-tutorlms-course-builder',
			'CertPSUCourseBuilder',
			array( 'groups' => $groups )
		);
	}

	/**
	 * Inject current values into the course details response so the builder can
	 * pre-fill the fields.
	 *
	 * @param array<string,mixed> $data Course data.
	 * @return array<string,mixed>
	 */
	public function prefill( array $data ): array {
		$course_id = isset( $data['ID'] ) ? (int) $data['ID'] : 0;
		if ( $course_id < 1 ) {
			return $data;
		}

		$settings = Course_Settings::for_course( $course_id );
		foreach ( self::FIELD_MAP as $field => $meta ) {
			$value = $settings[ $meta['key'] ] ?? '';
			$data[ $field ] = $meta['bool'] ? (bool) $value : (string) ( is_string( $value ) ? $value : '' );
		}

		return $data;
	}

	/**
	 * Persist builder field values, merging into the per-course settings meta.
	 *
	 * @param int $post_id Course ID.
	 * @return void
	 */
	public function save( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Only act on a course-builder save: at least one builder field present.
		// (The WP-admin metabox uses nested `certpsu_course[...]` keys instead.)
		$present = false;
		foreach ( array_keys( self::FIELD_MAP ) as $field ) {
			if ( isset( $_POST[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Tutor handles the builder save nonce.
				$present = true;
				break;
			}
		}
		if ( ! $present ) {
			return;
		}

		$overrides = Course_Settings::course_overrides( $post_id );
		$groups    = Course_Settings::groups();

		foreach ( self::FIELD_MAP as $field => $meta ) {
			$key = $meta['key'];

			if ( $meta['bool'] ) {
				// Switch: presence + truthiness; absent = off.
				$overrides[ $key ] = ! empty( $_POST[ $field ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				continue;
			}

			// Text-like: only overwrite when the field was submitted.
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}
			$raw = sanitize_text_field( wp_unslash( (string) $_POST[ $field ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( 'template_group' === $key ) {
				$overrides[ $key ] = array_key_exists( $raw, $groups ) ? $raw : 'participant';
			} else {
				$overrides[ $key ] = $raw;
			}
		}

		update_post_meta( $post_id, Course_Settings::META_KEY, $overrides );
	}
}
