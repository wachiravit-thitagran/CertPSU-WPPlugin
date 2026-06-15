<?php
/**
 * Smoke tests for Bootstrap behavior.
 *
 * @package CertPSU\Connector\Tests\Unit\Smoke
 */

declare(strict_types=1);

namespace CertPSU\Connector\Tests\Unit\Smoke;

use PHPUnit\Framework\TestCase;

/**
 * Verifies the plugin entrypoint boots successfully.
 */
final class BootstrapSmokeTest extends TestCase {

	/**
	 * Ensure the plugin boots and function exists.
	 *
	 * @return void
	 */
	public function test_plugin_helper_function_exists(): void {
		require_once dirname( __DIR__, 3 ) . '/plugins/certpsu-connector/certpsu-connector.php';

		self::assertTrue( function_exists( 'certpsu' ) );
		self::assertInstanceOf( \CertPSU\Connector\Plugin::class, certpsu() );
	}
}
