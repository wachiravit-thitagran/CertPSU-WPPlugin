# CertPSU TutorLMS Bridge

Issues cert.psu.ac.th certificates to Tutor LMS learners when they complete a
course. Requires the **CertPSU Connector** plugin (active) for the API client.

## Model: one class per course, on-the-fly issuance

- Each course maps to a single CertPSU **class**. The class is created the first
  time a learner completes the course and its id is stored on the course
  (`_certpsu_class_id`).
- When a learner completes the course, the bridge:
  1. ensures the course's class exists (`POST /v2/classes`),
  2. adds the learner as a participant (`POST /v2/classes/{class_id}/participants`),
  3. releases that learner's certificate on-the-fly
     (`POST /v2/classes/{class_id}/participants/{participant_id}/release`, using
     the server-assigned participant id returned by step 2).
- Work runs in the background via Action Scheduler (provided by the connector),
  with retries on transient failures and idempotency per learner + course.

## Configuration

Everything is configurable **per course** on the course editor (the *CertPSU
Certificate* box), with **global defaults** under **Settings → CertPSU
Certificates** that seed every course.

### Where to configure

- **Tutor LMS course builder (3.x)** — a curated set of CertPSU fields appears in
  the **Additional** section (after the certificate selector): enable, template
  id, group, name, certificate text, auto-release, organization id and
  certificate email template. Registered via the Tutor course-builder field API
  (`tutor_after_course_builder_load` + `Tutor.CourseBuilder.Additional.registerField`)
  and saved back to the same course meta.
- **WP-admin course editor** — the full *CertPSU Certificate* metabox (all
  sections incl. endorsers, class dates) on `wp-admin → Courses → edit`.
- **Settings → CertPSU Certificates** — global defaults.

All three write to the same `_certpsu_course_settings` post meta, so they stay
in sync. The builder exposes the common fields; advanced fields live in the
metabox / defaults.

Configurable fields:

- **General** — enable/disable issuance for the course.
- **Connection** — organization id (optional under API v2), certificate +
  endorser email templates.
- **Class** — name, printed name, description, started/ended/issued dates
  (blank = completion date), class date text, instructors (blank = course
  instructors), tags, duplicate policy, auto-send mail, endorse method,
  auto-release.
- **Certificate template** — template id, name, group, certificate/declaration
  text, printed organization name, remark.
- **Learner mapping** — participant name source (display name / first + last)
  and the user-meta key holding the common ID (national ID).
- **Endorsers** — repeatable list (endorser_id, user, name, position,
  requirement, auto-send).

## Hooks

```php
do_action( 'certpsu_tutorlms_class_created', $course_id, $class_id );
do_action( 'certpsu_tutorlms_participant_added', $course_id, $user_id, $class_id );
do_action( 'certpsu_tutorlms_certificate_released', $course_id, $user_id, $class_id, $participant_id );
```

## Requirements

- PHP 8.2+, WordPress 6.5+
- Tutor LMS (fires `tutor_course_complete_after`)
- CertPSU Connector (active)
