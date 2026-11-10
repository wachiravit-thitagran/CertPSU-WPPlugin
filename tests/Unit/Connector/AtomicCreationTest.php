<?php
declare(strict_types=1);
namespace CertPSU\Connector\Tests\Unit\Connector;

final class AtomicCreationTest extends \PHPUnit\Framework\TestCase {
	private $db;
	protected function setUp(): void {
		$this->db        = $GLOBALS['wpdb'];
		$GLOBALS['wpdb'] = new class() extends \Mock_WPDB {
			public function prepare( $sql, ...$args ) {
				return $sql;}
			public function get_var( $sql ) {
				return 0;}
		};
	}
	protected function tearDown(): void {
		$GLOBALS['wpdb'] = $this->db;
		unset( $GLOBALS['mock_certpsu_api'] );}
	public function test_busy_external_reference_does_not_insert_or_enqueue(): void {
		$v = $this->createMock( \CertPSU\Connector\Support\Validator::class );
		$v->method( 'validate_create_issuance' )->willReturn(
			array(
				'external_source'  => 's',
				'external_id'      => '1',
				'idempotency_mode' => 'return_existing',
				'participants'     => array(),
			)
		);
		$i = $this->createMock( \CertPSU\Connector\Database\Repositories\Issuance_Repository::class );
		$i->expects( self::never() )->method( 'insert' );
		$s = $this->createMock( \CertPSU\Connector\Support\Settings::class );
		$s->method( 'api_key' )->willReturn( 'key' );
		$service = new \CertPSU\Connector\Application\Create_Issuance_Service( $v, $i, $this->createMock( \CertPSU\Connector\Database\Repositories\Certificate_Repository::class ), $this->createMock( \CertPSU\Connector\Queue\Queue::class ), $s );
		$r       = $service->handle( array() );
		self::assertInstanceOf( \WP_Error::class, $r );
		self::assertSame( 'certpsu_reference_busy', $r->get_error_code() );
	}
	public function test_busy_course_claim_never_calls_remote_create(): void {
		$api = $this->createMock( \CertPSU\Connector\CertPSU\CertPSU_Api_Client::class );
		$api->expects( self::never() )->method( 'create_class' );
		$GLOBALS['mock_certpsu_api'] = $api;
		$builder                     = $this->createMock( \CertPSU\TutorLMS\Issuance\Class_Payload_Builder::class );
		$result                      = ( new \CertPSU\TutorLMS\Issuance\Course_Class_Manager( $builder ) )->ensure_class( 98765, array() );
		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'certpsu_class_locked', $result->get_error_code() );
	}
}
