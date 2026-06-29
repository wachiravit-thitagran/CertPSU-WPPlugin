<?php
/**
 * Per-course settings schema, defaults and resolution.
 *
 * @package CertPSU\TutorLMS
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS\Settings;

/**
 * Defines every configurable field for course certificate issuance, reads/writes
 * the values, and resolves the effective settings for a course (course-level
 * overrides on top of global defaults).
 */
final class Course_Settings {

	/**
	 * Post meta key holding per-course settings.
	 */
	public const META_KEY = '_certpsu_course_settings';

	/**
	 * Option holding global default settings.
	 */
	public const OPTION_KEY = 'certpsu_tutorlms_defaults';

	/**
	 * Tutor LMS course post type.
	 */
	public const COURSE_POST_TYPE = 'courses';

	/**
	 * Certificate group values supported by CertPSU.
	 *
	 * @return array<string,string>
	 */
	public static function groups(): array {
		return array(
			'attendance'              => 'Attendance',
			'pass_examination'        => 'Pass examination',
			'participant'             => 'Participant',
			'achievement'             => 'Achievement',
			'winner'                  => 'Winner',
			'first_runner_up'         => 'First runner-up',
			'second_runner_up'        => 'Second runner-up',
			'honorable_mention_prize' => 'Honorable mention',
			'advisor'                 => 'Advisor',
		);
	}

	/**
	 * Endorser position slots supported by CertPSU (valid endorser_id values).
	 *
	 * @return array<string,string>
	 */
	public static function endorser_positions(): array {
		return array(
			'endorser_1' => 'Endorser 1',
			'endorser_2' => 'Endorser 2',
			'endorser_3' => 'Endorser 3',
			'endorser_4' => 'Endorser 4',
			'endorser_5' => 'Endorser 5',
		);
	}

	/**
	 * Field schema grouped into sections. Used to render both the course metabox
	 * and the global defaults page, and to sanitize input.
	 *
	 * Each field: key, label, type, plus optional options/help/default.
	 * Scope `connection` fields are the ones that most often live in global
	 * defaults (organization + email templates).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function schema(): array {
		return array(
			'general'     => array(
				'title'  => 'General',
				'fields' => array(
					'enabled' => array(
						'label' => 'Issue a certificate when a learner completes this course',
						'type'  => 'checkbox',
						'help'  => 'When off, completing this course does not create an issuance.',
					),
				),
			),
			'connection'  => array(
				'title'  => 'CertPSU connection',
				'fields' => array(
					'certificate_email_template' => array(
						'label'          => 'Certificate email template',
						'type'           => 'text',
						'options_source' => 'email_template:participant',
						'help'           => 'Select from the organization email templates. Falls back to manual id entry when the API is unavailable.',
					),
					'endorser_required_endorsement_email_template' => array(
						'label'          => 'Endorser (required) email template',
						'type'           => 'text',
						'options_source' => 'email_template:endorser_required_endorsement',
						'help'           => 'Select from the organization email templates. Falls back to manual id entry when the API is unavailable.',
					),
					'endorser_without_endorsement_email_template' => array(
						'label'          => 'Endorser (without endorsement) email template',
						'type'           => 'text',
						'options_source' => 'email_template:endorser_without_endorsement',
						'help'           => 'Select from the organization email templates. Falls back to manual id entry when the API is unavailable.',
					),
				),
			),
			'class'       => array(
				'title'  => 'Class',
				'fields' => array(
					'class_name'                  => array(
						'label' => 'Class name',
						'type'  => 'text',
						'help'  => 'Leave blank to use the course title. Placeholders: {course_title}, {course_id}, {current_date}, {completed_course_day}, {completed_course_month}, {completed_course_month_short}, {completed_course_month_th}, {completed_course_month_th_short}, {completed_course_year}, {completed_course_year_bd}',
					),
					'printed_name'                => array(
						'label' => 'Printed name',
						'type'  => 'textarea',
						'help'  => 'Leave blank to use the course title. Placeholders: {course_title}, {course_id}, {current_date}, {completed_course_day}, {completed_course_month}, {completed_course_month_short}, {completed_course_month_th}, {completed_course_month_th_short}, {completed_course_year}, {completed_course_year_bd}',
					),
					'description'                 => array(
						'label' => 'Description',
						'type'  => 'textarea',
						'help'  => 'Placeholders: {class_name}, {printed_name}, {course_title}, {course_id}, {current_date}, {completed_course_day}, {completed_course_month}, {completed_course_month_short}, {completed_course_month_th}, {completed_course_month_th_short}, {completed_course_year}, {completed_course_year_bd}',
					),
					'started_date'                => array(
						'label' => 'Started date',
						'type'  => 'date',
						'help'  => 'Leave blank to use the course creation date.',
					),
					'ended_date'                  => array(
						'label' => 'Ended date',
						'type'  => 'date',
						'help'  => 'Leave blank to use the course creation date.',
					),
					'issued_date'                 => array(
						'label' => 'Issued date',
						'type'  => 'date',
						'help'  => 'Leave blank to use the course creation date.',
					),
					'class_date_text'             => array(
						'label' => 'Class date text',
						'type'  => 'text',
						'help'  => 'Placeholders: {class_name}, {printed_name}, {course_title}, {course_id}, {current_date}, {completed_course_day}, {completed_course_month}, {completed_course_month_short}, {completed_course_month_th}, {completed_course_month_th_short}, {completed_course_year}, {completed_course_year_bd}',
					),
					'instructors'                 => array(
						'label' => 'Instructors',
						'type'  => 'list',
						'help'  => 'One per line. Leave blank to use the course instructors.',
					),
					'tags'                        => array(
						'label' => 'Tags',
						'type'  => 'list',
						'help'  => 'One per line.',
					),
					'allow_duplicate_participant' => array(
						'label'   => 'Allow duplicate participant',
						'type'    => 'select',
						'options' => array(
							'not_allowed' => 'Not allowed',
							'allowed'     => 'Allowed',
						),
						'default' => 'not_allowed',
					),
					'auto_send_mail_participant'  => array(
						'label'   => 'Auto-send mail to participant',
						'type'    => 'select',
						'options' => array(
							'auto'     => 'Auto',
							'not_auto' => 'Do not auto-send',
						),
						'default' => 'auto',
					),
					'endorse_method'              => array(
						'label'   => 'Endorse method',
						'type'    => 'select',
						'options' => array(
							'auto' => 'Auto',
							'user' => 'User',
						),
						'default' => 'auto',
					),
					'auto_release'                => array(
						'label'   => 'Auto-release certificates',
						'type'    => 'checkbox',
						'default' => true,
						'help'    => 'Release immediately after participants are added. Turn off to release manually.',
					),
				),
			),
			'certificate' => array(
				'title'  => 'Certificate template',
				'fields' => array(
					'template_id'       => array(
						'label'          => 'Template',
						'type'           => 'text',
						'options_source' => 'certificate_template',
						'help'           => 'Select a certificate template from the organization. Falls back to manual id entry when the API is unavailable.',
					),
					'template_name'     => array(
						'label'       => 'CertPSU certificate name',
						'type'        => 'text',
						'placeholder' => 'เกียรติบัตรผู้เข้าร่วม',
						'default'     => 'เกียรติบัตรผู้เข้าร่วม',
						'help'        => 'Placeholders: {class_name}, {printed_name}, {course_title}, {course_id}, {current_date}, {completed_course_day}, {completed_course_month}, {completed_course_month_short}, {completed_course_month_th}, {completed_course_month_th_short}, {completed_course_year}, {completed_course_year_bd}',
					),
					'organization_name' => array(
						'label'       => 'CertPSU organization name (printed)',
						'type'        => 'text',
						'placeholder' => 'สำนักนวัตกรรมดิจิทัลและระบบอัจฉริยะ',
						'default'     => 'สำนักนวัตกรรมดิจิทัลและระบบอัจฉริยะ',
						'help'        => 'Placeholders: {class_name}, {printed_name}, {course_title}, {course_id}, {current_date}, {completed_course_day}, {completed_course_month}, {completed_course_month_short}, {completed_course_month_th}, {completed_course_month_th_short}, {completed_course_year}, {completed_course_year_bd}',
					),
					'declaration_text'  => array(
						'label'       => 'CertPSU declaration text',
						'type'        => 'text',
						'placeholder' => 'มอบเกียรติบัตรฉบับนี้ให้ไว้เพื่อแสดงว่า',
						'default'     => 'มอบเกียรติบัตรฉบับนี้ให้ไว้เพื่อแสดงว่า',
						'help'        => 'Placeholders: {class_name}, {printed_name}, {course_title}, {course_id}, {current_date}, {completed_course_day}, {completed_course_month}, {completed_course_month_short}, {completed_course_month_th}, {completed_course_month_th_short}, {completed_course_year}, {completed_course_year_bd}',
					),
					'certificate_text'  => array(
						'label'       => 'CertPSU certificate text',
						'type'        => 'textarea',
						'placeholder' => 'ได้ปฏิบัติงานอาสาผ่านระดับผู้เชี่ยวชาญถอดลายลักษณ์',
						'default'     => 'ได้ปฏิบัติงานอาสาผ่านระดับผู้เชี่ยวชาญถอดลายลักษณ์',
						'help'        => 'Placeholders: {class_name}, {printed_name}, {course_title}, {course_id}, {current_date}, {completed_course_day}, {completed_course_month}, {completed_course_month_short}, {completed_course_month_th}, {completed_course_month_th_short}, {completed_course_year}, {completed_course_year_bd}',
					),
					'template_group'    => array(
						'label'   => 'Group',
						'type'    => 'select',
						'options' => self::groups(),
						'default' => 'participant',
					),
					'remark'            => array(
						'label' => 'Remark',
						'type'  => 'text',
					),
				),
			),
			'learner'     => array(
				'title'  => 'Learner mapping',
				'fields' => array(
					'name_source'        => array(
						'label'   => 'Participant name source',
						'type'    => 'select',
						'options' => array(
							'display_name' => 'Display name',
							'first_last'   => 'First + last name',
						),
						'default' => 'display_name',
					),
					'common_id_meta_key' => array(
						'label' => 'Common ID user-meta key',
						'type'  => 'text',
						'help'  => 'User meta field holding the national ID / common ID (optional).',
					),
				),
			),
			'endorsers'   => array(
				'title'  => 'Endorsers',
				'fields' => array(
					'endorsers' => array(
						'label' => 'Endorsers',
						'type'  => 'endorsers',
						'help'  => 'Optional. Each endorser needs endorser_id, user (WordPress/CertPSU user id) and name.',
					),
				),
			),
		);
	}

	/**
	 * Flat default values for every field.
	 *
	 * @return array<string,mixed>
	 */
	public static function field_defaults(): array {
		$defaults = array();
		foreach ( self::schema() as $section ) {
			foreach ( $section['fields'] as $key => $field ) {
				$type = $field['type'] ?? 'text';
				if ( array_key_exists( 'default', $field ) ) {
					$defaults[ $key ] = $field['default'];
				} elseif ( 'checkbox' === $type ) {
					$defaults[ $key ] = false;
				} elseif ( 'list' === $type || 'endorsers' === $type ) {
					$defaults[ $key ] = array();
				} else {
					$defaults[ $key ] = '';
				}
			}
		}
		return $defaults;
	}

	/**
	 * Global default settings (option) merged over field defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function global_defaults(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		$stored = is_array( $stored ) ? $stored : array();
		return array_merge( self::field_defaults(), $stored );
	}

	/**
	 * Raw per-course overrides (only keys the editor saved).
	 *
	 * @param int $course_id Course ID.
	 * @return array<string,mixed>
	 */
	public static function course_overrides( int $course_id ): array {
		$stored = get_post_meta( $course_id, self::META_KEY, true );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Effective settings for a course: global defaults with course overrides on top.
	 *
	 * @param int $course_id Course ID.
	 * @return array<string,mixed>
	 */
	public static function for_course( int $course_id ): array {
		return array_merge( self::global_defaults(), self::course_overrides( $course_id ) );
	}
}
