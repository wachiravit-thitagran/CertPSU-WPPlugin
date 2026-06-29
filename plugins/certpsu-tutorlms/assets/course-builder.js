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

		var add    = Tutor.CourseBuilder.Additional.registerField.bind( Tutor.CourseBuilder.Additional );
		var cb     = window.CertPSUCourseBuilder || {};
		var groups = cb.groups || [];
		var slot   = 'after_certificates';

		var priority = 10;
		function nextPrio() {
			priority += 10; return priority; }

		// Render a dropdown when the connector supplied options, else a plain text input.
		function withBlank( opts ) {
			return [ { value: '', label: '\u2014 Select \u2014' } ].concat( opts || [] );
		}
		function selectOrText( field, options ) {
			if ( options && options.length ) {
				return { name: field.name, type: 'select', label: field.label, options: withBlank( options ), priority: field.priority, desc: field.desc };
			}
			return { name: field.name, type: 'text', label: field.label, placeholder: field.placeholder, priority: field.priority, desc: field.desc };
		}

		var placeholdersTooltip = "Available placeholders: {course_title}, {course_id}, {current_date}, {completed_course_day}, {completed_course_month}, {completed_course_month_short}, {completed_course_month_th}, {completed_course_month_th_short}, {completed_course_year}, {completed_course_year_bd}";
		var extendedPlaceholdersTooltip = placeholdersTooltip + ", {class_name}, {printed_name}";

		add( slot, { name: 'certpsu_enabled', type: 'switch', label: 'CertPSU: issue certificate on completion', priority: nextPrio() } );

		add( slot, selectOrText( { name: 'certpsu_template_id', label: 'CertPSU template', placeholder: 'cert template id', priority: nextPrio() }, cb.certificateTemplates ) );
		add( slot, { name: 'certpsu_template_name', type: 'text', label: 'CertPSU certificate name', placeholder: 'เกียรติบัตรผู้เข้าร่วม', priority: nextPrio(), desc: extendedPlaceholdersTooltip } );
		add( slot, { name: 'certpsu_organization_name', type: 'text', label: 'CertPSU organization name (printed)', placeholder: 'สำนักนวัตกรรมดิจิทัลและระบบอัจฉริยะ', priority: nextPrio(), desc: extendedPlaceholdersTooltip } );
		add( slot, { name: 'certpsu_declaration_text', type: 'text', label: 'CertPSU declaration text', placeholder: 'มอบเกียรติบัตรฉบับนี้ให้ไว้เพื่อแสดงว่า', priority: nextPrio(), desc: extendedPlaceholdersTooltip } );
		add( slot, { name: 'certpsu_certificate_text', type: 'textarea', label: 'CertPSU certificate text', placeholder: 'ได้ปฏิบัติงานอาสาผ่านระดับผู้เชี่ยวชาญถอดลายลักษณ์', priority: nextPrio(), desc: extendedPlaceholdersTooltip } );
		add( slot, { name: 'certpsu_template_group', type: 'select', label: 'CertPSU certificate group', options: groups, priority: nextPrio() } );
		add( slot, { name: 'certpsu_remark', type: 'text', label: 'CertPSU remark', priority: nextPrio(), desc: extendedPlaceholdersTooltip } );

		add( slot, selectOrText( { name: 'certpsu_certificate_email_template', label: 'CertPSU certificate email template', placeholder: 'email template id', priority: nextPrio() }, cb.emailCertificate ) );
		add( slot, selectOrText( { name: 'certpsu_endorser_required_endorsement_email_template', label: 'Endorser (required) email template', placeholder: 'email template id', priority: nextPrio() }, cb.emailRequired ) );
		add( slot, selectOrText( { name: 'certpsu_endorser_without_endorsement_email_template', label: 'Endorser (without endorsement) email template', placeholder: 'email template id', priority: nextPrio() }, cb.emailWithout ) );

		add( slot, { name: 'certpsu_class_name', type: 'text', label: 'Class name', placeholder: 'Leave blank to use course title', priority: nextPrio(), desc: placeholdersTooltip } );
		add( slot, { name: 'certpsu_printed_name', type: 'textarea', label: 'Printed name', placeholder: 'Leave blank to use course title', priority: nextPrio(), desc: placeholdersTooltip } );
		add( slot, { name: 'certpsu_description', type: 'textarea', label: 'Description', priority: nextPrio(), desc: extendedPlaceholdersTooltip } );

		add( slot, { name: 'certpsu_started_date', type: 'text', label: 'Started date (YYYY-MM-DD)', placeholder: 'Leave blank to use course creation date', priority: nextPrio() } );
		add( slot, { name: 'certpsu_ended_date', type: 'text', label: 'Ended date (YYYY-MM-DD)', placeholder: 'Leave blank to use course creation date', priority: nextPrio() } );
		add( slot, { name: 'certpsu_issued_date', type: 'text', label: 'Issued date (YYYY-MM-DD)', placeholder: 'Leave blank to use course creation date', priority: nextPrio() } );
		add( slot, { name: 'certpsu_class_date_text', type: 'text', label: 'Class date text', desc: 'Placeholders: {date_day}, {date_month}, {date_year}, etc.', priority: nextPrio() } );

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

	// Hack: Tutor LMS Course Builder (React) doesn't support a native "group/card" field type.
	// We use a MutationObserver/Interval to find our fields and style their containers to look like a card.
	var style       = document.createElement( 'style' );
	style.innerHTML = `
		.certpsu - cb - row {
			background - color: #FFFFFF;
			padding: 24px 32px;
			border - left: 1px solid #E1E6EB;
			border - right: 1px solid #E1E6EB;
			border - bottom: 1px solid #E1E6EB;
			margin - bottom: 0 ! important;
	}
		.certpsu - cb - first {
			border - top: 1px solid #E1E6EB;
			border - radius: 6px 6px 0 0;
			margin - top: 24px ! important;
			position: relative;
	}
		.certpsu - cb - first::before {
			content: "CertPSU Certificate Settings";
			display: block;
			font - size: 16px;
			font - weight: 500;
			color: #212327;
			margin - bottom: 16px;
			padding - bottom: 16px;
			border - bottom: 1px solid #E1E6EB;
	}
		.certpsu - cb - last {
			border - radius: 0 0 6px 6px;
			margin - bottom: 24px ! important;
	}
	`;
	document.head.appendChild( style );

	setInterval(
		function () {
			var fields = document.querySelectorAll( '[name^="certpsu_"]' );
			if (fields.length === 0) {
				return;
			}

			var rows = [];
			fields.forEach(
				function (field) {
					// Find the closest wrapper that is a sibling to other rows (usually a div wrapping the label and input)
					var row = field.closest( '.tutor-field-wrapper, .tutor-field, .tutor-form-row' ) || field.parentNode;
					if (row && rows.indexOf( row ) === -1) {
						rows.push( row );
					}
				}
			);

			if (rows.length > 0) {
				rows.forEach(
					function (row, index) {
						if ( ! row.classList.contains( 'certpsu-cb-row' )) {
							row.classList.add( 'certpsu-cb-row' );
						}
						if (index === 0 && ! row.classList.contains( 'certpsu-cb-first' )) {
							row.classList.add( 'certpsu-cb-first' );
						}
						if (index === rows.length - 1 && ! row.classList.contains( 'certpsu-cb-last' )) {
							row.classList.add( 'certpsu-cb-last' );
						}
					}
				);
			}
		},
		1000
	);
} )();
