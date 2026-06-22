<?php
declare(strict_types=1);

namespace CertPSU\Connector\Tests\Unit\Connector;

use PHPUnit\Framework\TestCase;
use CertPSU\Connector\Application\Create_Issuance_Service;
use CertPSU\Connector\Support\Validator;
use CertPSU\Connector\Database\Repositories\Issuance_Repository;
use CertPSU\Connector\Database\Repositories\Certificate_Repository;
use CertPSU\Connector\Queue\Queue;
use CertPSU\Connector\Support\Settings;
use CertPSU\Connector\DTO\Issuance_Result;

class CreateIssuanceServiceTest extends TestCase {

	private Create_Issuance_Service $service;
	private $validator;
	private $issuances;
	private $certificates;
	private $queue;
	private $settings;

	protected function setUp(): void {
		parent::setUp();
		
		$this->validator = $this->createMock( Validator::class );
		$this->issuances = $this->createMock( Issuance_Repository::class );
		$this->certificates = $this->createMock( Certificate_Repository::class );
		$this->queue = $this->createMock( Queue::class );
		$this->settings = $this->createMock( Settings::class );

		$this->service = new Create_Issuance_Service(
			$this->validator,
			$this->issuances,
			$this->certificates,
			$this->queue,
			$this->settings
		);
	}

	public function test_missing_api_key_fails(): void {
		$this->settings->method( 'api_key' )->willReturn( '' );
		$result = $this->service->handle( array() );
		
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'certpsu_missing_api_key', $result->get_error_code() );
	}

	public function test_validation_fails(): void {
		$this->settings->method( 'api_key' )->willReturn( 'key' );
		$this->validator->method( 'validate_create_issuance' )->willReturn( new \WP_Error( 'invalid', 'msg' ) );

		$result = $this->service->handle( array() );
		
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid', $result->get_error_code() );
	}

	public function test_successful_creation(): void {
		$this->settings->method( 'api_key' )->willReturn( 'key' );
		$this->validator->method( 'validate_create_issuance' )->willReturn( array(
			'external_source' => 'tutorlms',
			'external_id' => '123',
			'idempotency_mode' => 'return_existing',
			'participants' => array( array( 'name' => 'John' ) ),
		) );

		$this->issuances->method( 'find_latest_by_external_ref' )->willReturn( null );
		$this->issuances->method( 'insert' )->willReturn( 99 );
		$this->queue->method( 'enqueue_process_issuance' )->willReturn( true );

		// Mock $wpdb->query
		$GLOBALS['wpdb']->last_query = '';

		$result = $this->service->handle( array() );

		$this->assertInstanceOf( Issuance_Result::class, $result );
		$this->assertSame( 99, $result->issuance_id );
		$this->assertSame( 'queued', $result->status );
		$this->assertSame( 'tutorlms', $result->external_source );
	}

	public function test_returns_existing_issuance(): void {
		$this->settings->method( 'api_key' )->willReturn( 'key' );
		$this->validator->method( 'validate_create_issuance' )->willReturn( array(
			'external_source' => 'tutorlms',
			'external_id' => '123',
			'idempotency_mode' => 'return_existing',
			'participants' => array(),
		) );

		$this->issuances->method( 'find_latest_by_external_ref' )->willReturn( array(
			'id' => 88,
			'status' => 'processing',
			'participant_count' => 1
		) );

		$result = $this->service->handle( array() );

		$this->assertInstanceOf( Issuance_Result::class, $result );
		$this->assertSame( 88, $result->issuance_id );
		$this->assertTrue( $result->is_existing );
	}
}
