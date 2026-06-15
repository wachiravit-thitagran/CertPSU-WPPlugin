<?php
/**
 * Smoke tests for TutorLMS bridge bootstrap behavior.
 *
 * @package CertPSU\TutorLMS\Tests\Unit\Smoke
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS\Tests\Unit\Smoke;

use PHPUnit\Framework\TestCase;

/**
 * Verifies the plugin entrypoint boots successfully.
 */
final class TutorLmsSmokeTest extends TestCase {

	/**
	 * Ensure the plugin boots and function exists.
	 *
	 * @return void
	 */
	public function test_plugin_helper_function_exists(): void {
		require_once dirname( __DIR__, 3 ) . '/plugins/certpsu-tutorlms/certpsu-tutorlms.php';

		self::assertTrue( function_exists( '\\CertPSU\\TutorLMS\\boot_certpsu_tutorlms' ) );
	}
}
