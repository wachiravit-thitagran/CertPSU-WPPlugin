<?php
/**
 * E2E-only CertPSU HTTP stub. Loaded only into disposable WordPress container.
 * Fail closed on any unexpected call to the fake CertPSU host.
 */
add_filter('pre_http_request', function ($pre, $args, $url) {
    $host = wp_parse_url($url, PHP_URL_HOST);
    if ('certpsu-e2e.invalid' !== $host) {
        return $pre;
    }
    $path = (string) wp_parse_url($url, PHP_URL_PATH);
    $method = strtoupper((string) ($args['method'] ?? 'GET'));
    $calls = get_option('certpsu_e2e_api_calls', array());
    $calls[] = $method . ' ' . $path;
    update_option('certpsu_e2e_api_calls', $calls, false);
    $payload = null;
    $code = 200;
    if ($method === 'POST' && $path === '/v2/classes') {
        $payload = array('id' => 'e2e-class-001');
    } elseif ($method === 'POST' && $path === '/v2/classes/e2e-class-001/participants') {
        $body = json_decode((string) ($args['body'] ?? ''), true);
        if (empty($body['participants'][0]['email'])) {
            $code = 400;
            $payload = array('error' => array('code' => 'missing_email', 'message' => 'Missing participant email'));
        } else {
            $payload = array(array('id' => 'e2e-participant-001'));
        }
    } elseif ($method === 'POST' && $path === '/v2/classes/e2e-class-001/participants/e2e-participant-001/release') {
        $payload = array('certificate_url' => 'https://certpsu-e2e.invalid/certificates/e2e-001');
    } else {
        $code = 404;
        $payload = array('error' => array('code' => 'unexpected_mock_route', 'message' => $method . ' ' . $path));
    }
    return array(
        'headers' => array('content-type' => 'application/json'),
        'body' => wp_json_encode(array('data' => $payload)),
        'response' => array('code' => $code, 'message' => $code === 200 ? 'OK' : 'Not Found'),
        'cookies' => array(),
        'filename' => null,
    );
}, 10, 3);
