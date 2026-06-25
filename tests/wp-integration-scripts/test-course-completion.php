<?php
// 1. Create a test student
$user_id = wp_insert_user([
    'user_login' => 'teststudent',
    'user_email' => 'test@example.com',
    'user_pass'  => 'password',
    'role'       => 'subscriber'
]);
if (is_wp_error($user_id)) {
    echo "Error creating user: " . $user_id->get_error_message() . "\n";
    exit(1);
}
echo "Created User ID: $user_id\n";

// 2. Create a test course
$course_id = wp_insert_post([
    'post_type'   => 'courses',
    'post_title'  => 'Test Course',
    'post_status' => 'publish'
]);
if (is_wp_error($course_id)) {
    echo "Error creating course: " . $course_id->get_error_message() . "\n";
    exit(1);
}
echo "Created Course ID: $course_id\n";

// 3. Enable CertPSU for this course
update_post_meta($course_id, '_certpsu_course_settings', ['enabled' => true]);

// 4. Trigger the course completion hook
do_action('tutor_course_complete_after', $course_id, $user_id);

// 5. Verify the issuance job was queued in Action Scheduler
if ( ! as_has_scheduled_action('certpsu_tutorlms_issue') ) {
    echo "Error: Certificate issuance job (certpsu_tutorlms_issue) was NOT queued!\n";
    exit(1);
} else {
    echo "Success: Certificate issuance job was queued successfully.\n";
}
