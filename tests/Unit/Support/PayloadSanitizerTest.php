<?php
/**
 * Payload Sanitizer Test.
 *
 * @package CertPSU\Connector\Tests\Unit\Support
 */

declare(strict_types=1);

namespace CertPSU\Connector\Tests\Unit\Support;

use CertPSU\Connector\Support\Payload_Sanitizer;
use PHPUnit\Framework\TestCase;

/**
 * Tests Payload_Sanitizer.
 */
final class PayloadSanitizerTest extends TestCase {

	/**
	 * Test sanitizes keys.
	 *
	 * @return void
	 */
	public function test_sanitizes_sensitive_keys(): void {
		$sanitizer = new Payload_Sanitizer();

		$payload = array(
			'name'     => 'Alice',
			'password' => 'secret',
			'meta'     => array(
				'token' => 'abc',
			),
		);

		$sanitized = $sanitizer->sanitize( $payload );

		self::assertSame( 'Alice', $sanitized['name'] );
		self::assertSame( '***', $sanitized['password'] );
		self::assertSame( '***', $sanitized['meta']['token'] );
	}
}
