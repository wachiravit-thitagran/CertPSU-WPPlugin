/* global document, window, Tutor */
/**
 * Registers CertPSU certificate fields inside the Tutor LMS (3.x) React course
 * builder, in the Additional section (after the certificate selector).
 *
 * Field names are prefixed `certpsu_`. Their current values are injected into
 * the course details response (see Tutor_Course_Builder::prefill) and saved via
 * save_post_courses (see Tutor_Course_Builder::save).
 */
( function () {
	'use strict';

	function register() {
		if ( typeof Tutor === 'undefined' || ! Tutor.CourseBuilder || ! Tutor.CourseBuilder.Additional ) {
			return;
		}

		var add = Tutor.CourseBuilder.Additional.registerField.bind( Tutor.CourseBuilder.Additional );
		var groups = ( window.CertPSUCourseBuilder && window.CertPSUCourseBuilder.groups ) || [];
		var slot = 'after_certificates';

		add( slot, {
			name: 'certpsu_enabled',
			type: 'switch',
			label: 'CertPSU: issue certificate on completion',
			priority: 10,
		} );

		add( slot, {
			name: 'certpsu_template_id',
			type: 'text',
			label: 'CertPSU template ID',
			placeholder: 'cert template id',
			priority: 20,
		} );

		add( slot, {
			name: 'certpsu_template_group',
			type: 'select',
			label: 'CertPSU certificate group',
			options: groups,
			priority: 30,
		} );

		add( slot, {
			name: 'certpsu_template_name',
			type: 'text',
			label: 'CertPSU certificate name',
			placeholder: 'Leave blank to use course title',
			priority: 40,
		} );

		add( slot, {
			name: 'certpsu_certificate_text',
			type: 'textarea',
			label: 'CertPSU certificate text',
			priority: 50,
		} );

		add( slot, {
			name: 'certpsu_auto_release',
			type: 'switch',
			label: 'CertPSU: auto-release certificate',
			priority: 60,
		} );

		add( slot, {
			name: 'certpsu_organization_id',
			type: 'text',
			label: 'CertPSU organization ID (optional)',
			priority: 70,
		} );

		add( slot, {
			name: 'certpsu_certificate_email_template',
			type: 'text',
			label: 'CertPSU certificate email template',
			priority: 80,
		} );
	}

	if ( document.readyState !== 'loading' ) {
		register();
	} else {
		document.addEventListener( 'DOMContentLoaded', register );
	}
} )();
