<?php
declare(strict_types=1);

namespace CertPSU\Connector\Tests\Unit\TutorLMS;

use PHPUnit\Framework\TestCase;
use CertPSU\TutorLMS\Listener;
use CertPSU\TutorLMS\Settings\Course_Settings;

class ListenerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['mock_actions'] = array();
		$GLOBALS['mock_async_actions'] = array();
		$GLOBALS['mock_post_meta'] = array();
	}

	public function test_register_hooks(): void {
		$listener = new Listener();
		$listener->register();

		$this->assertArrayHasKey( 'tutor_course_complete_after', $GLOBALS['mock_actions'] );
		$this->assertNotEmpty( $GLOBALS['mock_actions']['tutor_course_complete_after'] );
	}

	public function test_on_course_completed_skips_if_disabled(): void {
		$listener = new Listener();

		$GLOBALS['mock_post_meta'][123][Course_Settings::META_KEY] = array( 'enabled' => false );

		$listener->on_course_completed( 123, 456 );

		$this->assertEmpty( $GLOBALS['mock_async_actions'] );
	}

	public function test_on_course_completed_enqueues_action_if_enabled(): void {
		$listener = new Listener();

		$GLOBALS['mock_post_meta'][123][Course_Settings::META_KEY] = array( 'enabled' => true );

		$listener->on_course_completed( 123, 456 );

		$this->assertCount( 1, $GLOBALS['mock_async_actions'] );
		$action = $GLOBALS['mock_async_actions'][0];
		
		$this->assertSame( 'certpsu_tutorlms_issue', $action['hook'] );
		$this->assertSame( 123, $action['args'][0]['course_id'] );
		$this->assertSame( 456, $action['args'][0]['user_id'] );
		$this->assertSame( 'certpsu-tutorlms', $action['group'] );
	}

	public function test_on_course_completed_invalid_course_id(): void {
		$listener = new Listener();
		$listener->on_course_completed( 0, 456 );
		$this->assertEmpty( $GLOBALS['mock_async_actions'] );
	}
}
