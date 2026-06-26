<?php
declare(strict_types=1);

namespace CertPSU\Connector\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use CertPSU\Connector\Frontend\My_Certificates_Shortcode;
use CertPSU\Connector\Database\Repositories\Certificate_Repository;



class MyCertificatesShortcodeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['mock_is_user_logged_in'] = true;
		$GLOBALS['mock_current_user_id'] = 123;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['mock_is_user_logged_in'], $GLOBALS['mock_current_user_id'] );
		parent::tearDown();
	}

	public function test_register_adds_shortcode(): void {
		$repository = $this->createMock( Certificate_Repository::class );
		$shortcode  = new My_Certificates_Shortcode( $repository );

		$shortcode->register();

		$this->assertArrayHasKey( 'certpsu_my_certificates', $GLOBALS['mock_shortcodes'] );
		$this->assertSame( array( $shortcode, 'render' ), $GLOBALS['mock_shortcodes']['certpsu_my_certificates'] );
	}

	public function test_render_requires_login(): void {
		$GLOBALS['mock_is_user_logged_in'] = false;
		$repository = $this->createMock( Certificate_Repository::class );
		
		// The repository shouldn't be called if not logged in
		$repository->expects( $this->never() )->method( 'get_by_wp_user_id' );

		$shortcode = new My_Certificates_Shortcode( $repository );
		$output    = $shortcode->render();

		$this->assertStringContainsString( 'Please log in', $output );
	}

	public function test_render_fetches_certificates_using_injected_repository(): void {
		$repository = $this->createMock( Certificate_Repository::class );
		
		// Ensure get_by_wp_user_id is called on the injected instance to prevent the fatal error
		$repository->expects( $this->once() )
			->method( 'get_by_wp_user_id' )
			->with( 123 )
			->willReturn( array() );

		$shortcode = new My_Certificates_Shortcode( $repository );
		
		try {
			$shortcode->render();
		} catch ( \Throwable $e ) {
			// Catch template loading errors since we are in a unit test environment
			// and only care about the repository dependency injection.
		}
	}
}
