<?php
declare(strict_types=1);

namespace CertPSU\Connector\Tests\Unit\TutorLMS;

use PHPUnit\Framework\TestCase;
use CertPSU\TutorLMS\Admin\Defaults_Page;
use CertPSU\TutorLMS\Settings\Settings_Sanitizer;

class DefaultsPageTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['mock_submenu_pages'] = array();
		$GLOBALS['mock_registered_settings'] = array();
	}

	public function test_register_hooks(): void {
		$page = new Defaults_Page();
		$page->register();

		$this->assertArrayHasKey( 'admin_menu', $GLOBALS['mock_actions'] );
		$this->assertArrayHasKey( 'admin_init', $GLOBALS['mock_actions'] );
	}

	public function test_add_page(): void {
		$page = new Defaults_Page();
		$page->add_page();

		$this->assertCount( 1, $GLOBALS['mock_submenu_pages'] );
		$this->assertSame( 'certpsu-connector-settings', $GLOBALS['mock_submenu_pages'][0][0] );
		$this->assertSame( 'certpsu-tutorlms-defaults', $GLOBALS['mock_submenu_pages'][0][4] );
	}

	public function test_register_setting(): void {
		$page = new Defaults_Page();
		$page->register_setting();

		$this->assertCount( 1, $GLOBALS['mock_registered_settings'] );
		$this->assertSame( 'certpsu_tutorlms_defaults_group', $GLOBALS['mock_registered_settings'][0][0] );
		$this->assertSame( 'certpsu_tutorlms_defaults', $GLOBALS['mock_registered_settings'][0][1] );
	}

	public function test_sanitize_calls_sanitizer(): void {
		$sanitizer = $this->createMock( Settings_Sanitizer::class );
		$sanitizer->method( 'sanitize' )->willReturn( array( 'enabled' => true ) );

		$page = new Defaults_Page( $sanitizer );
		$result = $page->sanitize( array( 'enabled' => 'yes' ) );

		$this->assertSame( array( 'enabled' => true ), $result );
	}
}
