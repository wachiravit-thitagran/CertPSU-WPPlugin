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

$mode = getenv('CERTPSU_E2E_MODE') ?: 'mock';
if (!in_array($mode, array('mock', 'dev'), true)) {
    $fail('Unknown CERTPSU_E2E_MODE; expected mock or dev');
}
$base_url = 'https://certpsu-e2e.invalid';
$api_key = 'not-a-real-key';
$template_id = 'fixture-template-001';
$email = 'learner@example.test';
if ($mode === 'dev') {
    $base_url = rtrim(trim((string) getenv('CERTPSU_DEV_URL')), '/');
    $host = (string) wp_parse_url($base_url, PHP_URL_HOST);
    $scheme = (string) wp_parse_url($base_url, PHP_URL_SCHEME);
    if ($scheme !== 'https' || !$host || wp_parse_url($base_url, PHP_URL_USER) ||
        wp_parse_url($base_url, PHP_URL_PASS) || wp_parse_url($base_url, PHP_URL_QUERY) ||
        wp_parse_url($base_url, PHP_URL_FRAGMENT) ||
        preg_match('/(^|\\.)cert\\.psu\\.ac\\.th$/i', $host)) {
        $fail('Dev mode requires an HTTPS staging API URL; the production cert.psu.ac.th host is blocked');
    }
    $api_key = (string) getenv('CERTPSU_DEV_API_KEY');
    $template_id = trim((string) getenv('CERTPSU_DEV_TEMPLATE_ID'));
    $email = trim((string) getenv('CERTPSU_E2E_TEST_EMAIL'));
    if ($api_key === '' || $template_id === '' || !is_email($email)) {
        $fail('Dev mode requires Jenkins API credential, staging template ID, and valid test email');
    }
}
update_option('certpsu_connector_settings', array(
    'api_base_url' => $base_url,
    'api_key' => $api_key,
), false);
if ($mode === 'mock') {
    update_option('certpsu_e2e_api_calls', array(), false);
}
WP_CLI::log('CertPSU E2E mode: ' . $mode . '; host: ' . wp_parse_url($base_url, PHP_URL_HOST));
$run = strtolower(substr(hash('sha256', (string) microtime(true) . wp_generate_password(8)), 0, 12));
$user_id = wp_create_user('e2e-certpsu-' . $run, wp_generate_password(30), $email);
if (is_wp_error($user_id)) {
    $fail('Cannot create learner: ' . $user_id->get_error_message());
}
wp_update_user(array('ID' => $user_id, 'display_name' => 'E2E CertPSU Learner'));
$course_id = wp_insert_post(array(
    'post_type' => 'courses',
    'post_status' => 'publish',
    'post_title' => 'E2E CertPSU Test Course ' . $run,
    'post_name' => 'e2e-certpsu-course-' . $run,
    'post_author' => 1,
), true);
if (is_wp_error($course_id)) {
    $fail('Cannot create Tutor LMS course: ' . $course_id->get_error_message());
}
update_post_meta($course_id, '_certpsu_course_settings', array(
    'enabled' => true, 'auto_release' => true,
    'template_id' => $template_id,
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
    empty($record['class_id']) || empty($record['participant_id'])) {
    $fail('CertPSU issuance not released/persisted: ' . wp_json_encode($record));
}
$calls = get_option('certpsu_e2e_api_calls', array());
if ($mode === 'mock' && count($calls) !== 3) {
    $fail('Expected three mock CertPSU requests, got: ' . wp_json_encode($calls));
}
$prior_state = $record;
do_action('certpsu_tutorlms_issue', array('course_id' => $course_id, 'user_id' => $user_id, 'attempt' => 1));
$after = get_user_meta($user_id, '_certpsu_tutorlms_issued', true);
if (($after[$course_id] ?? array()) !== $prior_state) {
    $fail('Idempotency failed: certificate state changed after replay');
}
if ($mode === 'mock' && get_option('certpsu_e2e_api_calls', array()) !== $calls) {
    $fail('Idempotency failed: repeat issued another mock API request');
}
WP_CLI::success('WordPress/MySQL E2E passed with CertPSU mode ' . $mode);
