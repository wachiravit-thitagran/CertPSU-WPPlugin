<?php
/**
 * Builds the CertPSU "create class" request body from course settings.
 *
 * @package CertPSU\TutorLMS
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS\Issuance;

/**
 * Translates a course + its effective settings into the body expected by
 * `POST /v2/classes` (one class per course).
 */
final class Class_Payload_Builder {

	/**
	 * Build the create-class body.
	 *
	 * @param int                 $course_id Course ID.
	 * @param array<string,mixed> $settings  Effective course settings.
	 * @return array<string,mixed>
	 */
	public function build( int $course_id, array $settings ): array {
		$title       = (string) get_the_title( $course_id );
		$course_post = get_post( $course_id );
		$timestamp   = $course_post ? strtotime( $course_post->post_date ) : current_time( 'timestamp' );
		$today       = gmdate( 'Y-m-d', $timestamp );

		$thai_months       = array(
			1  => 'มกราคม',
			2  => 'กุมภาพันธ์',
			3  => 'มีนาคม',
			4  => 'เมษายน',
			5  => 'พฤษภาคม',
			6  => 'มิถุนายน',
			7  => 'กรกฎาคม',
			8  => 'สิงหาคม',
			9  => 'กันยายน',
			10 => 'ตุลาคม',
			11 => 'พฤศจิกายน',
			12 => 'ธันวาคม',
		);
		$thai_months_short = array(
			1  => 'ม.ค.',
			2  => 'ก.พ.',
			3  => 'มี.ค.',
			4  => 'เม.ย.',
			5  => 'พ.ค.',
			6  => 'มิ.ย.',
			7  => 'ก.ค.',
			8  => 'ส.ค.',
			9  => 'ก.ย.',
			10 => 'ต.ค.',
			11 => 'พ.ย.',
			12 => 'ธ.ค.',
		);

		$month_num = (int) gmdate( 'n', $timestamp );

		// 1) Establish base context without class_name first, so class_name can use course_title.
		$context = array(
			'course_title'                    => $title,
			'course_id'                       => $course_id,
			'current_date'                    => $today,
			'completed_course_day'            => gmdate( 'j', $timestamp ),
			'completed_course_month'          => gmdate( 'F', $timestamp ),
			'completed_course_month_short'    => gmdate( 'M', $timestamp ),
			'completed_course_month_th'       => $thai_months[ $month_num ],
			'completed_course_month_th_short' => $thai_months_short[ $month_num ],
			'completed_course_year'           => gmdate( 'Y', $timestamp ),
			'completed_course_year_bd'        => (string) ( (int) gmdate( 'Y', $timestamp ) + 543 ),
		);

		// Evaluate name and printed_name with base context.
		$raw_name         = (string) ( $settings['class_name'] ?? '' );
		$raw_printed_name = (string) ( $settings['printed_name'] ?? '' );

		$name         = $this->first_non_empty( certpsu()->replacer()->replace( $raw_name, $context ), $title );
		$printed_name = $this->first_non_empty( certpsu()->replacer()->replace( $raw_printed_name, $context ), $title );

		// 2) Expand context with the evaluated class_name and printed_name for the rest of the payload.
		$context['class_name']   = $name;
		$context['printed_name'] = $printed_name;

		$class = array(
			'name'                        => $name,
			'printed_name'                => $printed_name,
			'description'                 => $this->null_if_empty( (string) ( $settings['description'] ?? '' ) ),
			'started_date'                => $this->first_non_empty( (string) ( $settings['started_date'] ?? '' ), $today ),
			'ended_date'                  => $this->first_non_empty( (string) ( $settings['ended_date'] ?? '' ), $today ),
			'issued_date'                 => $this->first_non_empty( (string) ( $settings['issued_date'] ?? '' ), $today ),
			'class_date_text'             => $this->null_if_empty( (string) ( $settings['class_date_text'] ?? '' ) ),
			'instructors'                 => $this->resolve_instructors( $course_id, $settings ),
			'tags'                        => $this->as_list( $settings['tags'] ?? array() ),
			'allow_duplicate_participant' => (string) ( $settings['allow_duplicate_participant'] ?? 'not_allowed' ),
			'auto_send_mail_participant'  => (string) ( $settings['auto_send_mail_participant'] ?? 'auto' ),
			'endorse_method'              => (string) ( $settings['endorse_method'] ?? 'auto' ),
		);

		$endorsers = $this->as_list( $settings['endorsers'] ?? array() );
		if ( array() !== $endorsers ) {
			$class['endorsers'] = $endorsers;
		}

		$body = array(
			'certificate_email_template'                   => (string) ( $settings['certificate_email_template'] ?? '' ),
			'endorser_required_endorsement_email_template' => (string) ( $settings['endorser_required_endorsement_email_template'] ?? '' ),
			'endorser_without_endorsement_email_template'  => (string) ( $settings['endorser_without_endorsement_email_template'] ?? '' ),
			'class'                                        => $class,
		);

		$template = $this->build_template( $settings, $title );
		if ( null !== $template ) {
			$body['certificate_templates'] = array( $template );
		}

		return certpsu()->replacer()->replace_recursive( $body, $context );
	}

	/**
	 * Build the certificate template entry, or null when no template is configured.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param string              $title    Course title (template name fallback).
	 * @return array<string,mixed>|null
	 */
	private function build_template( array $settings, string $title ): ?array {
		$template_id = trim( (string) ( $settings['template_id'] ?? '' ) );
		if ( '' === $template_id ) {
			return null;
		}

		return array(
			'group'             => (string) ( $settings['template_group'] ?? 'participant' ),
			'template'          => $template_id,
			'name'              => $this->first_non_empty( (string) ( $settings['template_name'] ?? '' ), $title ),
			'certificate_text'  => (string) ( $settings['certificate_text'] ?? '' ),
			'declaration_text'  => (string) ( $settings['declaration_text'] ?? '' ),
			'organization_name' => (string) ( $settings['organization_name'] ?? '' ),
			'remark'            => $this->null_if_empty( (string) ( $settings['remark'] ?? '' ) ),
		);
	}

	/**
	 * Instructor display names: explicit setting, else the course's Tutor instructors.
	 *
	 * @param int                 $course_id Course ID.
	 * @param array<string,mixed> $settings  Settings.
	 * @return array<int,string>
	 */
	private function resolve_instructors( int $course_id, array $settings ): array {
		$explicit = $this->as_list( $settings['instructors'] ?? array() );
		if ( array() !== $explicit ) {
			return $explicit;
		}

		global $wpdb;
		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %d",
				'_tutor_instructor_course_id',
				$course_id
			)
		);

		$names = array();
		foreach ( (array) $ids as $id ) {
			$user = get_userdata( (int) $id );
			if ( $user ) {
				$names[] = (string) $user->display_name;
			}
		}

		if ( array() === $names ) {
			$author = get_userdata( (int) get_post_field( 'post_author', $course_id ) );
			if ( $author ) {
				$names[] = (string) $author->display_name;
			}
		}

		return $names;
	}

	/**
	 * Coerce to a clean list array.
	 *
	 * @param mixed $value Value.
	 * @return array<int,mixed>
	 */
	private function as_list( mixed $value ): array {
		return is_array( $value ) ? array_values( $value ) : array();
	}

	/**
	 * Return the first non-empty (trimmed) string.
	 *
	 * @param string $primary  Primary.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	private function first_non_empty( string $primary, string $fallback ): string {
		$primary = trim( $primary );
		return '' !== $primary ? $primary : $fallback;
	}

	/**
	 * Null when the string is empty after trimming.
	 *
	 * @param string $value Value.
	 * @return string|null
	 */
	private function null_if_empty( string $value ): ?string {
		$value = trim( $value );
		return '' === $value ? null : $value;
	}
}
