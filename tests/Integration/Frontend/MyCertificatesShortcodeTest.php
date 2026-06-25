<?php
/**
 * My Certificates Shortcode Test.
 *
 * @package CertPSU\Connector\Tests\Integration\Frontend
 */

declare(strict_types=1);

namespace CertPSU\Connector\Tests\Integration\Frontend;

use PHPUnit\Framework\TestCase;
use CertPSU\Connector\Frontend\My_Certificates_Shortcode;
use CertPSU\Connector\Bootstrap;

/**
 * Tests the shortcode.
 */
final class MyCertificatesShortcodeTest extends TestCase {

	/**
	 * Ensure the shortcode renders without fatal errors when logged out.
	 *
	 * @return void
	 */
	public function test_render_when_logged_out(): void {
		if ( ! function_exists( 'is_user_logged_in' ) ) {
			self::markTestSkipped( 'Requires WordPress environment.' );
		}

		$shortcode = new My_Certificates_Shortcode();
		$output    = $shortcode->render();

		self::assertStringContainsString( 'Please log in', $output );
	}

	/**
	 * Ensure the shortcode renders without fatal errors when logged in.
	 * This verifies that the container properly resolves the certificate repository.
	 *
	 * @return void
	 */
	public function test_render_does_not_throw_fatal_error_when_logged_in(): void {
		if ( ! function_exists( 'wp_insert_user' ) ) {
			self::markTestSkipped( 'Requires WordPress environment.' );
		}

		Bootstrap::init();

		// Create a mock user
		$user_id = wp_insert_user(
			array(
				'user_login' => 'test_user_' . uniqid(),
				'user_email' => 'test' . uniqid() . '@example.com',
				'user_pass'  => wp_generate_password(),
			)
		);

		self::assertIsInt( $user_id, 'Failed to create test user.' );

		// Log in as the test user
		wp_set_current_user( $user_id );

		$shortcode = new My_Certificates_Shortcode();
		
		try {
			// This will either return an HTML string or throw an error if dependencies are missing.
			$output = $shortcode->render();
			self::assertIsString( $output );
		} catch ( \Throwable $e ) {
			self::fail( 'Rendering shortcode threw an exception: ' . $e->getMessage() );
		} finally {
			// Clean up
			wp_set_current_user( 0 );
			wp_delete_user( $user_id );
		}
	}
}
