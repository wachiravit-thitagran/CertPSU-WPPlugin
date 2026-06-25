<?php
$settings = [
    'client_id'     => 'test_client',
    'client_secret' => 'test_secret',
    'environment'   => 'sandbox'
];
update_option('certpsu_connector_settings', $settings);
$saved = get_option('certpsu_connector_settings');
if ( empty($saved['client_id']) ) {
    echo "Settings failed to save.\n";
    exit(1);
} else {
    echo "Settings saved successfully.\n";
}
