<?php
/**
 * WordPress + MySQL + TutorLMS + Action Scheduler E2E, CertPSU HTTP mocked.
 * Executed by WP-CLI in an ephemeral Docker Compose stack.
 */
if (!defined('WP_CLI') || !WP_CLI) {
    throw new RuntimeException('WP-CLI only');
}
$fail = static function (string $message): void {
    WP_CLI::error($message);
};
if (wp_get_environment_type() !== 'development') {
    $fail('Refusing to run on a non-development WordPress');
}
if (!function_exists('is_plugin_active')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
foreach (array('tutor/tutor.php', 'certpsu-connector/certpsu-connector.php', 'certpsu-tutorlms/certpsu-tutorlms.php') as $plugin) {
    if (!is_plugin_active($plugin)) {
        $fail('Plugin is not active: ' . $plugin);
    }
}
if (!function_exists('as_get_scheduled_actions') || !class_exists('ActionScheduler')) {
    $fail('Action Scheduler unavailable');
}

update_option('certpsu_connector_settings', array(
    'api_base_url' => 'https://certpsu-e2e.invalid',
    'api_key' => 'not-a-real-key',
), false);
update_option('certpsu_e2e_api_calls', array(), false);
$user_id = wp_create_user('e2e-certpsu-learner', wp_generate_password(30), 'learner@example.test');
if (is_wp_error($user_id)) {
    $fail('Cannot create learner: ' . $user_id->get_error_message());
}
wp_update_user(array('ID' => $user_id, 'display_name' => 'E2E CertPSU Learner'));
$course_id = wp_insert_post(array(
    'post_type' => 'courses',
    'post_status' => 'publish',
    'post_title' => 'E2E CertPSU Test Course',
    'post_name' => 'e2e-certpsu-course',
    'post_author' => 1,
), true);
if (is_wp_error($course_id)) {
    $fail('Cannot create Tutor LMS course: ' . $course_id->get_error_message());
}
update_post_meta($course_id, '_certpsu_course_settings', array(
    'enabled' => true, 'auto_release' => true,
    'template_id' => 'fixture-template-001',
    'template_group' => 'participant',
    'instructors' => array('E2E Instructor'),
));
WP_CLI::log('Fixture created: course ' . $course_id . ', learner ' . $user_id);
do_action('tutor_course_complete_after', $course_id, $user_id);
$ids = as_get_scheduled_actions(array(
    'hook' => 'certpsu_tutorlms_issue',
    'group' => 'certpsu-tutorlms',
    'status' => 'pending',
    'per_page' => 20,
), 'ids');
$queued = false;
foreach ($ids as $id) {
    $action = ActionScheduler::store()->fetch_action($id);
    $arguments = $action->get_args();
    $job = $arguments[0] ?? array();
    if ((int) ($job['course_id'] ?? 0) === $course_id && (int) ($job['user_id'] ?? 0) === $user_id) {
        $queued = true;
        break;
    }
}
if (!$queued) {
    $fail('Completion hook did not enqueue the CertPSU issuance action');
}
WP_CLI::log('Tutor LMS completion queued an Action Scheduler job');
ActionScheduler::runner()->run('certpsu-tutorlms');
$state = get_user_meta($user_id, '_certpsu_tutorlms_issued', true);
$record = is_array($state) ? ($state[$course_id] ?? array()) : array();
if (empty($record['released']) ||
    ($record['class_id'] ?? '') !== 'e2e-class-001' ||
    ($record['participant_id'] ?? '') !== 'e2e-participant-001') {
    $fail('CertPSU issuance not released/persisted: ' . wp_json_encode($record));
}
$calls = get_option('certpsu_e2e_api_calls', array());
if (count($calls) !== 3) {
    $fail('Expected exactly three simulated CertPSU requests, got: ' . wp_json_encode($calls));
}
do_action('certpsu_tutorlms_issue', array('course_id' => $course_id, 'user_id' => $user_id, 'attempt' => 1));
if (get_option('certpsu_e2e_api_calls', array()) !== $calls) {
    $fail('Idempotency failed: repeat issued another API request');
}
WP_CLI::success('WordPress/MySQL/TutorLMS/Action Scheduler real; CertPSU API mocked; E2E passed.');
