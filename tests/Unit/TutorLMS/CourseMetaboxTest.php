<?php
declare(strict_types=1);

namespace CertPSU\Connector\Tests\Unit\TutorLMS;

use PHPUnit\Framework\TestCase;
use CertPSU\TutorLMS\Admin\Course_Metabox;
use CertPSU\TutorLMS\Settings\Settings_Sanitizer;

class CourseMetaboxTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['mock_meta_boxes'] = array();
		$GLOBALS['mock_post_meta'] = array();
		$_POST = array();
	}

	public function test_register_hooks(): void {
		$metabox = new Course_Metabox();
		$metabox->register();

		$this->assertArrayHasKey( 'add_meta_boxes', $GLOBALS['mock_actions'] );
		$this->assertArrayHasKey( 'save_post_courses', $GLOBALS['mock_actions'] );
	}

	public function test_add_metabox(): void {
		$metabox = new Course_Metabox();
		$metabox->add_metabox();

		$this->assertCount( 1, $GLOBALS['mock_meta_boxes'] );
		$this->assertSame( 'certpsu-course-settings', $GLOBALS['mock_meta_boxes'][0][0] );
		$this->assertSame( 'courses', $GLOBALS['mock_meta_boxes'][0][3] );
	}

	public function test_save_validates_nonce_and_saves(): void {
		$sanitizer = $this->createMock( Settings_Sanitizer::class );
		$sanitizer->method( 'sanitize' )->willReturn( array( 'enabled' => true ) );

		$metabox = new Course_Metabox( $sanitizer );

		$_POST['certpsu_course_settings_nonce'] = 'valid_nonce';
		$_POST['certpsu_course'] = array( 'enabled' => '1' );

		$metabox->save( 123 );

		$this->assertArrayHasKey( 123, $GLOBALS['mock_post_meta'] );
		$this->assertSame( array( 'enabled' => true ), $GLOBALS['mock_post_meta'][123]['_certpsu_course_settings'] );
	}

	public function test_save_bails_without_nonce(): void {
		$metabox = new Course_Metabox();

		$metabox->save( 123 );

		$this->assertEmpty( $GLOBALS['mock_post_meta'] );
	}

	public function test_save_bails_with_invalid_nonce(): void {
		$metabox = new Course_Metabox();

		$_POST['certpsu_course_settings_nonce'] = 'invalid_nonce';

		$metabox->save( 123 );

		$this->assertEmpty( $GLOBALS['mock_post_meta'] );
	}
}
