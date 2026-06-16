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

		var priority = 10;
		function nextPrio() { priority += 10; return priority; }

		add( slot, { name: 'certpsu_enabled', type: 'switch', label: 'CertPSU: issue certificate on completion', priority: nextPrio() } );

		add( slot, { name: 'certpsu_template_id', type: 'text', label: 'CertPSU template ID', placeholder: 'cert template id', priority: nextPrio() } );
		add( slot, { name: 'certpsu_template_group', type: 'select', label: 'CertPSU certificate group', options: groups, priority: nextPrio() } );
		add( slot, { name: 'certpsu_template_name', type: 'text', label: 'CertPSU certificate name', placeholder: 'Leave blank to use course title', priority: nextPrio() } );
		add( slot, { name: 'certpsu_certificate_text', type: 'textarea', label: 'CertPSU certificate text', priority: nextPrio() } );
		add( slot, { name: 'certpsu_declaration_text', type: 'text', label: 'CertPSU declaration text', priority: nextPrio() } );
		add( slot, { name: 'certpsu_organization_name', type: 'text', label: 'CertPSU organization name (printed)', priority: nextPrio() } );
		add( slot, { name: 'certpsu_remark', type: 'text', label: 'CertPSU remark', priority: nextPrio() } );

		add( slot, { name: 'certpsu_certificate_email_template', type: 'text', label: 'CertPSU certificate email template', priority: nextPrio() } );
		add( slot, { name: 'certpsu_endorser_required_endorsement_email_template', type: 'text', label: 'Endorser (required) email template', priority: nextPrio() } );
		add( slot, { name: 'certpsu_endorser_without_endorsement_email_template', type: 'text', label: 'Endorser (without endorsement) email template', priority: nextPrio() } );

		add( slot, { name: 'certpsu_class_name', type: 'text', label: 'Class name', placeholder: 'Leave blank to use course title', priority: nextPrio() } );
		add( slot, { name: 'certpsu_printed_name', type: 'text', label: 'Printed name', placeholder: 'Leave blank to use course title', priority: nextPrio() } );
		add( slot, { name: 'certpsu_description', type: 'textarea', label: 'Description', priority: nextPrio() } );
		
		add( slot, { name: 'certpsu_started_date', type: 'text', label: 'Started date (YYYY-MM-DD)', priority: nextPrio() } );
		add( slot, { name: 'certpsu_ended_date', type: 'text', label: 'Ended date (YYYY-MM-DD)', priority: nextPrio() } );
		add( slot, { name: 'certpsu_issued_date', type: 'text', label: 'Issued date (YYYY-MM-DD)', priority: nextPrio() } );
		add( slot, { name: 'certpsu_class_date_text', type: 'text', label: 'Class date text', priority: nextPrio() } );
		
		add( slot, { name: 'certpsu_instructors', type: 'textarea', label: 'Instructors (One per line)', priority: nextPrio() } );
		add( slot, { name: 'certpsu_tags', type: 'textarea', label: 'Tags (One per line)', priority: nextPrio() } );

		add( slot, { name: 'certpsu_allow_duplicate_participant', type: 'select', label: 'Allow duplicate participant', options: [{value:'not_allowed', label:'Not allowed'}, {value:'allowed', label:'Allowed'}], priority: nextPrio() } );
		add( slot, { name: 'certpsu_auto_send_mail_participant', type: 'select', label: 'Auto-send mail to participant', options: [{value:'auto', label:'Auto'}, {value:'not_auto', label:'Do not auto-send'}], priority: nextPrio() } );
		add( slot, { name: 'certpsu_endorse_method', type: 'select', label: 'Endorse method', options: [{value:'auto', label:'Auto'}, {value:'user', label:'User'}], priority: nextPrio() } );
		add( slot, { name: 'certpsu_auto_release', type: 'switch', label: 'CertPSU: auto-release certificates', priority: nextPrio() } );

		add( slot, { name: 'certpsu_name_source', type: 'select', label: 'Participant name source', options: [{value:'display_name', label:'Display name'}, {value:'first_last', label:'First + last name'}], priority: nextPrio() } );
		add( slot, { name: 'certpsu_common_id_meta_key', type: 'text', label: 'Common ID user-meta key', priority: nextPrio() } );
	}

	if ( document.readyState !== 'loading' ) {
		register();
	} else {
		document.addEventListener( 'DOMContentLoaded', register );
	}
} )();
