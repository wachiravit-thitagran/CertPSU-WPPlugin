<?php
declare(strict_types=1);

namespace CertPSU\Connector\Tests\Unit\TutorLMS;

use PHPUnit\Framework\TestCase;
use CertPSU\TutorLMS\Integration\My_Certificates_Integration;

class MyCertificatesIntegrationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['mock_filters'] = array();
		$GLOBALS['mock_user_meta'] = array();
		$GLOBALS['mock_post_titles'] = array();
	}

	public function test_register_hooks(): void {
		$integration = new My_Certificates_Integration();
		$integration->register();

		$this->assertArrayHasKey( 'certpsu_my_certificates_data', $GLOBALS['mock_filters'] );
	}

	public function test_inject_returns_original_if_no_meta(): void {
		$integration = new My_Certificates_Integration();
		$certs = array( array( 'id' => '1', 'title' => 'Original' ) );
		
		$result = $integration->inject_tutorlms_certificates( $certs, 123 );

		$this->assertSame( $certs, $result );
	}

	public function test_inject_adds_released_certificates(): void {
		$integration = new My_Certificates_Integration();
		$certs = array( array( 'id' => '1', 'title' => 'Original' ) );

		$GLOBALS['mock_post_titles'][99] = 'Tutor Course';
		$GLOBALS['mock_user_meta'][123]['_certpsu_tutorlms_issued'] = array(
			99 => array(
				'released' => true,
				'participant_id' => 'part_123',
				'certificate_url' => 'https://cert.com',
				'at' => '2023-01-01 12:00:00'
			),
			100 => array(
				'released' => false,
			)
		);

		$result = $integration->inject_tutorlms_certificates( $certs, 123 );

		$this->assertCount( 2, $result );
		$this->assertSame( 'part_123', $result[1]['id'] );
		$this->assertSame( 'Course 99', $result[1]['title'] );
		$this->assertSame( 'https://cert.com', $result[1]['certificate_url'] );
		$this->assertSame( '2023-01-01 12:00:00', $result[1]['issued_at'] );
		$this->assertSame( 'tutorlms', $result[1]['source'] );
	}
}
