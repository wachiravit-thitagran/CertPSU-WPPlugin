<?php
global $wp_scripts;
$builder = new \CertPSU\TutorLMS\Integration\Tutor_Course_Builder();
$builder->enqueue();
$data = $wp_scripts->get_data("certpsu-tutorlms-course-builder", "data");

if (strpos($data, "emailCertificate") !== false && strpos($data, "emailRequired") !== false && strpos($data, "emailWithout") !== false) {
    if (strpos($data, "emailParticipant") === false) {
        echo "TutorCourseBuilder enqueued localized script correctly.\n";
    } else {
        echo "TutorCourseBuilder must NOT use the old emailParticipant key.\n";
        exit(1);
    }
} else {
    echo "TutorCourseBuilder missing correct localized variables. Data: " . $data . "\n";
    exit(1);
}
