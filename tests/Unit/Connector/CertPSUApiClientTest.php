<?php
declare(strict_types=1);

namespace CertPSU\Connector\Tests\Unit\Connector;

use PHPUnit\Framework\TestCase;
use CertPSU\Connector\CertPSU\CertPSU_Api_Client;
use CertPSU\Connector\Support\Settings;
use CertPSU\Connector\Database\Repositories\Api_Log_Repository;
use CertPSU\Connector\Support\Payload_Sanitizer;

class CertPSUApiClientTest extends TestCase {

	private CertPSU_Api_Client $client;

	protected function setUp(): void {
		parent::setUp();
		
		$GLOBALS['mock_options'] = array(
			'certpsu_connector_settings' => array(
				'api_base_url' => 'https://api.test.com',
				'api_key'      => 'test_api_key',
			)
		);
		$settings = new Settings();
		$logs = new Api_Log_Repository();
		$sanitizer = new Payload_Sanitizer();

		$this->client = new CertPSU_Api_Client( $settings, $logs, $sanitizer );
	}

	public function test_create_class_success(): void {
		$GLOBALS['mock_http_response'] = array(
			'response' => array( 'code' => 201 ),
			'body'     => wp_json_encode( array(
				'data' => array( 'id' => 'class_123', 'status' => 'created' ),
				'meta' => array( 'request_id' => 'req_abc' )
			) )
		);

		$payload = array( 'name' => 'Test Class' );
		$response = $this->client->create_class( $payload );

		$this->assertTrue( $response->success );
		$this->assertSame( 201, $response->status_code );
		$this->assertSame( 'class_123', $response->data['id'] );
	}

	public function test_get_participants_handles_v2_error_envelope(): void {
		$GLOBALS['mock_http_response'] = array(
			'response' => array( 'code' => 404 ),
			'body'     => wp_json_encode( array(
				'error' => array( 'code' => 'not_found', 'message' => 'Class not found' )
			) )
		);

		$response = $this->client->get_participants( 'class_invalid' );

		$this->assertFalse( $response->success );
		$this->assertSame( 404, $response->status_code );
		$this->assertSame( 'not_found', $response->error_code );
		$this->assertSame( 'Class not found', $response->error_message );
	}

	public function test_wp_error_response_is_handled(): void {
		$GLOBALS['mock_http_response'] = new \WP_Error( 'http_timeout', 'Connection timed out' );

		$response = $this->client->get_class_release( 'class_123' );

		$this->assertFalse( $response->success );
		$this->assertSame( 'http_timeout', $response->error_code );
		$this->assertSame( 'Connection timed out', $response->error_message );
	}

	public function test_add_participants(): void {
		$GLOBALS['mock_http_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( array(
				'data' => array( array( 'id' => 'part_1' ), array( 'id' => 'part_2' ) )
			) )
		);

		$response = $this->client->add_participants( 'class_123', array( 'participants' => array() ) );

		$this->assertTrue( $response->success );
		$this->assertCount( 2, $response->data );
		$this->assertSame( 'part_1', $response->data[0]['id'] );
	}
}
