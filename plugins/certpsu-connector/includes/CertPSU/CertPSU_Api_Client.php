<?php
/**
 * CertPSU API Client.
 *
 * @package CertPSU\Connector\CertPSU
 */

declare(strict_types=1);

namespace CertPSU\Connector\CertPSU;

use CertPSU\Connector\Support\Json;

/**
 * HTTP Client.
 */
final class CertPSU_Api_Client {

	/**
	 * Constructor.
	 *
	 * @param \CertPSU\Connector\Support\Settings                         $settings Settings.
	 * @param \CertPSU\Connector\Database\Repositories\Api_Log_Repository $logs Logs repo.
	 * @param \CertPSU\Connector\Support\Payload_Sanitizer                $sanitizer Sanitizer.
	 */
	public function __construct(
		private \CertPSU\Connector\Support\Settings $settings,
		private \CertPSU\Connector\Database\Repositories\Api_Log_Repository $logs,
		private \CertPSU\Connector\Support\Payload_Sanitizer $sanitizer
	) {}

	/**
	 * Create class.
	 *
	 * API v2 receives every value through the request body. The organization is
	 * derived from the API key server-side, so it is no longer sent. The
	 * certificate template (formerly a separate call) is folded into the body
	 * via `certificate_templates`.
	 *
	 * @param array<string,mixed> $body Request body.
	 * @return Api_Response
	 */
	public function create_class( array $body ): Api_Response {
		return $this->request( 'POST', '/v2/classes', array(), $body );
	}

	/**
	 * Add participants.
	 *
	 * @param string              $class_id ID.
	 * @param array<string,mixed> $payload Body.
	 * @return Api_Response
	 */
	public function add_participants( string $class_id, array $payload ): Api_Response {
		return $this->request( 'POST', "/v2/classes/{$class_id}/participants", array(), $payload );
	}

	/**
	 * Release participants.
	 *
	 * Triggers asynchronous certificate generation/sending (HTTP 202).
	 *
	 * @param string $class_id ID.
	 * @return Api_Response
	 */
	public function release_participants( string $class_id ): Api_Response {
		return $this->request( 'POST', "/v2/classes/{$class_id}/release", array(), null );
	}

	/**
	 * Release a single participant's certificate on-the-fly.
	 *
	 * Idempotent server-side: a participant that already has a certificate is
	 * not re-issued. API v2 references the participant by its server-assigned
	 * id (returned by add_participants), not by email/common_id.
	 *
	 * @param string $class_id Class ID.
	 * @param string $participant_id Server-assigned participant id.
	 * @return Api_Response
	 */
	public function release_participant( string $class_id, string $participant_id ): Api_Response {
		return $this->request( 'POST', "/v2/classes/{$class_id}/participants/" . rawurlencode( $participant_id ) . '/release', array(), null );
	}

	/**
	 * Get one participant's release status / certificate link.
	 *
	 * @param string $class_id Class ID.
	 * @param string $participant_id Server-assigned participant id.
	 * @return Api_Response
	 */
	public function get_participant_release( string $class_id, string $participant_id ): Api_Response {
		return $this->request( 'GET', "/v2/classes/{$class_id}/participants/" . rawurlencode( $participant_id ) . '/release', array(), null );
	}

	/**
	 * Get participants.
	 *
	 * @param string $class_id ID.
	 * @return Api_Response
	 */
	public function get_participants( string $class_id ): Api_Response {
		return $this->request( 'GET', "/v2/classes/{$class_id}/participants", array(), null );
	}

	/**
	 * List certificate templates available to the organization (derived from the API key).
	 *
	 * @param array<string,mixed> $query Optional query (page, size, status, sort).
	 * @return Api_Response
	 */
	public function list_certificate_templates( array $query = array() ): Api_Response {
		return $this->request( 'GET', '/v2/certificate-templates', $query, null );
	}

	/**
	 * List email templates of the organization (derived from the API key).
	 *
	 * @param array<string,mixed> $query Optional query (page, size, type, is_default, sort).
	 * @return Api_Response
	 */
	public function list_email_templates( array $query = array() ): Api_Response {
		return $this->request( 'GET', '/v2/email-templates', $query, null );
	}

	/**
	 * List endorsers of the organization (derived from the API key).
	 *
	 * @param array<string,mixed> $query Optional query (page, size, status, sort).
	 * @return Api_Response
	 */
	public function list_endorsers( array $query = array() ): Api_Response {
		return $this->request( 'GET', '/v2/endorsers', $query, null );
	}

	/**
	 * Request.
	 *
	 * @param string                   $method HTTP Method.
	 * @param string                   $path Path.
	 * @param array<string,mixed>      $query Query.
	 * @param array<string,mixed>|null $payload Payload.
	 * @return Api_Response
	 */
	private function request( string $method, string $path, array $query, ?array $payload ): Api_Response {
		$base_url = defined( 'CERTPSU_CONNECTOR_API_BASE_URL' )
			? CERTPSU_CONNECTOR_API_BASE_URL
			: (string) apply_filters( 'certpsu_connector_api_base_url', 'https://cert.psu.ac.th:8443' );

		$request_id = 'req_' . str_replace( '.', '', uniqid( '', true ) );

		$url  = add_query_arg( $query, rtrim( $base_url, '/' ) . $path );
		$args = array(
			'method'  => $method,
			'headers' => array(
				'X-API-Key'    => $this->settings->api_key(),
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'X-Request-ID' => $request_id,
			),
			'timeout' => 20,
		);

		if ( null !== $payload ) {
			$args['body'] = Json::encode( $payload );
		}

		$started       = microtime( true );
		$http_response = wp_remote_request( $url, $args );
		$duration      = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( is_wp_error( $http_response ) ) {
			$log_id = $this->logs->insert(
				array(
					'method'             => $method,
					'endpoint'           => $path,
					'request_query_json' => Json::encode( $this->sanitizer->sanitize( $query ) ),
					'request_body_json'  => Json::encode( $this->sanitizer->sanitize( $payload ?? array() ) ),
					'response_status'    => null,
					'response_body_json' => null,
					'success'            => 0,
					'error_code'         => $http_response->get_error_code(),
					'error_message'      => $http_response->get_error_message(),
					'duration_ms'        => $duration,
				)
			);

			return new Api_Response( false, null, null, null, $http_response->get_error_code(), $http_response->get_error_message(), $log_id );
		}

		$status  = (int) wp_remote_retrieve_response_code( $http_response );
		$body    = (string) wp_remote_retrieve_body( $http_response );
		$decoded = json_decode( $body, true );

		$success = $status >= 200 && $status < 300;

		// API v2 wraps every successful payload in an envelope: { "data": <resource>, "meta": {...} }.
		// Unwrap `data` so callers keep working with the bare resource.
		if ( is_array( $decoded ) && array_key_exists( 'data', $decoded ) ) {
			$data = is_array( $decoded['data'] ) ? $decoded['data'] : array( 'value' => $decoded['data'] );
		} else {
			$data = is_array( $decoded ) ? $decoded : null;
		}

		// API v2 errors use: { "error": { "code", "message", "details", "request_id", ... } }.
		$error_code    = 'http_' . $status;
		$error_message = $body;
		if ( is_array( $decoded ) && isset( $decoded['error'] ) && is_array( $decoded['error'] ) ) {
			$error_code    = isset( $decoded['error']['code'] ) ? (string) $decoded['error']['code'] : $error_code;
			$error_message = isset( $decoded['error']['message'] ) ? (string) $decoded['error']['message'] : $error_message;
		}

		$log_id = $this->logs->insert(
			array(
				'method'             => $method,
				'endpoint'           => $path,
				'request_query_json' => Json::encode( $this->sanitizer->sanitize( $query ) ),
				'request_body_json'  => Json::encode( $this->sanitizer->sanitize( $payload ?? array() ) ),
				'response_status'    => $status,
				'response_body_json' => Json::encode( $this->sanitizer->sanitize( is_array( $decoded ) ? $decoded : array( 'raw' => $body ) ) ),
				'success'            => $success ? 1 : 0,
				'error_code'         => $success ? null : $error_code,
				'error_message'      => $success ? null : $error_message,
				'duration_ms'        => $duration,
			)
		);

		return new Api_Response( $success, $status, $data, null === $decoded ? $body : null, $success ? null : $error_code, $success ? null : $error_message, $log_id );
	}
}
