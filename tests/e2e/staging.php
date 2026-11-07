<?php
/**
 * WP-CLI E2E probe for a real, private CertPSU staging instance.
 *
 * This script intentionally does NOT create users/courses or change course
 * settings, and must never run against production.
 * Jenkins provides E2E_PHASE, E2E_COURSE_ID, E2E_USER_ID.
 */
declare(strict_types=1);

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    throw new RuntimeException( 'WP-CLI only.' );
}
if ( function_exists( 'wp_get_environment_type' ) && 'staging' !== wp_get_environment_type() ) {
    WP_CLI::error( 'Refusing to run outside WP_ENVIRONMENT_TYPE=staging.' );
}

$phase = getenv( 'E2E_PHASE' ) ?: 'preflight';
$course_id = filter_var( getenv( 'E2E_COURSE_ID' ), FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
$user_id = filter_var( getenv( 'E2E_USER_ID' ), FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
if ( ! $course_id || ! $user_id ) {
    WP_CLI::error( 'E2E_COURSE_ID and E2E_USER_ID must be positive integers.' );
}

$course = get_post( $course_id );
$user = get_userdata( $user_id );
if ( ! $course || 'courses' !== $course->post_type || 0 !== strpos( $course->post_name, 'e2e-certpsu' ) ) {
    WP_CLI::error( 'Use a dedicated Tutor LMS course with slug prefix e2e-certpsu.' );
}
if ( ! $user || 0 !== strpos( $user->user_login, 'e2e-certpsu' ) ) {
    WP_CLI::error( 'Use a dedicated learner with login prefix e2e-certpsu.' );
}
if ( ! function_exists( 'is_plugin_active' ) ) {\n    require_once ABSPATH . 'wp-admin/includes/plugin.php';\n}\nif ( ! is_plugin_active( 'certpsu-connector/certpsu-connector.php' ) ||
     ! is_plugin_active( 'certpsu-tutorlms/certpsu-tutorlms.php' ) ||
     ! is_plugin_active( 'tutor/tutor.php' ) ) {
    WP_CLI::error( 'Connector, TutorLMS bridge, and Tutor LMS must be active.' );
}
if ( ! function_exists( 'as_enqueue_async_action' ) || ! class_exists( 'CertPSU\\TutorLMS\\Settings\\Course_Settings' ) ) {
    WP_CLI::error( 'CertPSU bridge or Action Scheduler is unavailable.' );
}

$settings = \CertPSU\TutorLMS\Settings\Course_Settings::for_course( $course_id );
if ( empty( $settings['enabled'] ) || empty( $settings['auto_release'] ) || empty( $settings['template_id'] ) ) {
    WP_CLI::error( 'Dedicated course must have CertPSU enabled, auto_release, and a valid template_id.' );
}

$state_for = static function () use ( $user_id, $course_id ): array {
    $all = get_user_meta( $user_id, '_certpsu_tutorlms_issued', true );
    return is_array( $all ) && isset( $all[ $course_id ] ) && is_array( $all[ $course_id ] )
        ? $all[ $course_id ] : array();
};

switch ( $phase ) {
    case 'preflight':
        if ( $state_for() ) {
            WP_CLI::error( 'Fixture has prior issuance state; use a fresh dedicated test learner.' );
        }
        WP_CLI::success( 'Staging fixture and required plugins verified.' );
        break;

    case 'trigger':
        if ( $state_for() ) {
            WP_CLI::error( 'Fixture already has issuance state. Not repeating the initial action.' );
        }
        // This is the actual Tutor LMS completion hook, which must enqueue
        // the CertPSU action through its registered listener.
        do_action( 'tutor_course_complete_after', $course_id, $user_id );
        if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
            WP_CLI::error( 'Action Scheduler cannot inspect enqueued actions.' );
        }
        $ids = as_get_scheduled_actions(
            array( 'hook' => 'certpsu_tutorlms_issue', 'group' => 'certpsu-tutorlms',
                   'status' => 'pending', 'per_page' => 100 ), 'ids'
        );
        $found = false;
        foreach ( $ids as $id ) {
            $action = ActionScheduler::store()->fetch_action( $id );
            $args = $action->get_args();
            $job = $args[0] ?? array();
            if ( (int) ( $job['course_id'] ?? 0 ) === $course_id &&
                 (int) ( $job['user_id'] ?? 0 ) === $user_id ) {
                $found = true;
                break;
            }
        }
        // The action may have started before inspection on a busy staging host.
        if ( ! $found && ! $state_for() ) {
            WP_CLI::error( 'Tutor LMS completion did not enqueue an issuance action.' );
        }
        WP_CLI::success( 'Tutor LMS completion triggered; CertPSU job queued/started.' );
        break;

    case 'verify':
        $state = $state_for();
        if ( empty( $state['released'] ) || empty( $state['class_id'] ) || empty( $state['participant_id'] ) ) {
            WP_CLI::error( 'Waiting for successful release and participant/class IDs.' );
        }
        WP_CLI::success( 'Certificate released; class and participant IDs persisted.' );
        break;

    case 'repeat':
        $before = $state_for();
        if ( empty( $before['released'] ) ) {
            WP_CLI::error( 'Certificate must be released before checking idempotency.' );
        }
        // Test handler idempotency synchronously without creating a second
        // scheduled job. It must preserve the previously released state.
        do_action( 'certpsu_tutorlms_issue', array(
            'course_id' => $course_id, 'user_id' => $user_id, 'attempt' => 1,
        ) );
        if ( $before !== $state_for() ) {
            WP_CLI::error( 'Idempotency failed: already-released state changed.' );
        }
        WP_CLI::success( 'Repeated completion handler preserved the issuance state.' );
        break;

    default:
        WP_CLI::error( 'Unknown E2E_PHASE.' );
}
