<?php
// Test-only process. The caller supplies a dedicated disposable WordPress DB config.
$config = (string) getenv( 'CERTPSU_WP_TEST_CONFIG' );
if ( ! $config || ! is_file( $config ) ) {
	throw new RuntimeException( 'CERTPSU_WP_TEST_CONFIG is required' ); }
require $config;
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require_once ABSPATH . 'wp-includes/plugin.php';
add_action(
	'muplugins_loaded',
	static function () {
		if ( getenv( 'CERTPSU_TUTOR_PLUGIN' ) ) {
			require getenv( 'CERTPSU_TUTOR_PLUGIN' ); }
		require dirname( __DIR__, 2 ) . '/plugins/certpsu-connector/certpsu-connector.php';
	}
);
require ABSPATH . 'wp-settings.php';
add_filter( 'pre_http_request', static fn()=>new WP_Error( 'offline_only', 'No external HTTP' ), -999 );
$input = json_decode( file_get_contents( $argv[1] ), true );
if ( $input['kind'] === 'create' ) {
	file_put_contents( $input['ready'], 'ready' );
	$result = certpsu()->create_issuance( $input['args'] );
	$output = is_wp_error( $result ) ? array( 'error' => $result->get_error_code() ) : array(
		'id'       => $result->issuance_id,
		'existing' => $result->is_existing,
	);
} elseif ( $input['kind'] === 'state' ) {
	$repo = new CertPSU\TutorLMS\Issuance\Learner_State_Repository();
	$repo->get( $input['user'], $input['course'] );
	file_put_contents( $input['ready'], 'ready' );
	$deadline = microtime( true ) + 8;
	while ( ! file_exists( $input['go'] ) && microtime( true ) < $deadline ) {
		usleep( 10000 );}
	$output = array( 'saved' => $repo->save( $input['user'], $input['course'], $input['data'] ) );
} elseif ( $input['kind'] === 'course' ) {
	$result = ( new CertPSU\TutorLMS\Issuance\Course_Class_Manager() )->ensure_class( $input['course'], array() );
	$output = is_wp_error( $result ) ? array( 'error' => $result->get_error_code() ) : array( 'id' => $result );
}
file_put_contents( $input['output'], json_encode( $output ) );
