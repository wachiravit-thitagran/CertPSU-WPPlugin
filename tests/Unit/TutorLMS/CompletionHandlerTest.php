<?php
declare(strict_types=1);

namespace CertPSU\Connector\Tests\Unit\TutorLMS;

use PHPUnit\Framework\TestCase;
use CertPSU\TutorLMS\Issuance\Completion_Handler;
use CertPSU\TutorLMS\Issuance\Course_Class_Manager;
use CertPSU\TutorLMS\Support\Learner;
use CertPSU\TutorLMS\Settings\Course_Settings;
use CertPSU\Connector\CertPSU\CertPSU_Api_Client;
use CertPSU\Connector\CertPSU\Api_Response;

class CompletionHandlerTest extends TestCase {

	private $classes;
	private $learner;
	private $api;

	protected function setUp(): void {
		parent::setUp();
		$this->classes = $this->createMock( Course_Class_Manager::class );
		$this->learner = $this->createMock( Learner::class );
		$this->api = $this->createMock( CertPSU_Api_Client::class );
		
		$GLOBALS['mock_certpsu_api'] = $this->api;
		$GLOBALS['mock_post_meta'] = array();
		$GLOBALS['mock_user_meta'] = array();
		$GLOBALS['mock_scheduled_actions'] = array();
	}

	public function test_handle_bails_if_disabled(): void {
		$handler = new Completion_Handler( $this->classes, $this->learner );

		$GLOBALS['mock_post_meta'][123][Course_Settings::META_KEY] = array( 'enabled' => false );

		$handler->handle( array( 'course_id' => 123, 'user_id' => 456 ) );

		$this->assertEmpty( $GLOBALS['mock_user_meta'] );
	}

	public function test_handle_bails_if_already_issued(): void {
		$handler = new Completion_Handler( $this->classes, $this->learner );

		$GLOBALS['mock_post_meta'][123][Course_Settings::META_KEY] = array( 'enabled' => true );
		$GLOBALS['mock_user_meta'][456]['_certpsu_tutorlms_issued'] = array(
			123 => array( 'released' => true )
		);

		$handler->handle( array( 'course_id' => 123, 'user_id' => 456 ) );

		// The learner method should not be called
		$this->learner->expects( $this->never() )->method( 'participant' );
	}

	public function test_handle_success_auto_release(): void {
		$handler = new Completion_Handler( $this->classes, $this->learner );

		$GLOBALS['mock_post_meta'][123][Course_Settings::META_KEY] = array( 
			'enabled' => true,
			'auto_release' => true 
		);

		$this->learner->method( 'participant' )->willReturn( array( 'name' => 'John' ) );
		$this->classes->method( 'ensure_class' )->willReturn( 'class_1' );

		// Add participants response
		$this->api->method( 'add_participants' )->willReturn(
			new Api_Response( true, 200, array( 'participants' => array( array( 'id' => 'part_1' ) ) ), null, null, null, 1 )
		);

		// Release response
		$this->api->method( 'release_participant' )->willReturn(
			new Api_Response( true, 200, array( 'certificate_url' => 'https://cert.com/123' ), null, null, null, 2 )
		);

		$handler->handle( array( 'course_id' => 123, 'user_id' => 456 ) );

		$this->assertNotEmpty( $GLOBALS['mock_user_meta'][456]['_certpsu_tutorlms_issued'][123] );
		$state = $GLOBALS['mock_user_meta'][456]['_certpsu_tutorlms_issued'][123];
		$this->assertTrue( $state['released'] );
		$this->assertSame( 'part_1', $state['participant_id'] );
		$this->assertSame( 'https://cert.com/123', $state['certificate_url'] );
	}
}
