<?php
/**
 * Create Issuance Service Test.
 *
 * @package CertPSU\Connector\Tests\Integration\Application
 */

declare(strict_types=1);

namespace CertPSU\Connector\Tests\Integration\Application;

use PHPUnit\Framework\TestCase;

/**
 * Tests for Create Issuance Service.
 */
final class CreateIssuanceServiceTest extends TestCase {

	/**
	 * Setup.
	 */
	protected function setUp(): void {
		if ( ! function_exists( 'certpsu' ) ) {
			self::markTestSkipped( 'Integration tests require WordPress.' );
		}
	}

	/**
	 * Valid payload.
	 *
	 * @return array<string,mixed>
	 */
	private function valid_payload(): array {
		return array(
			'external_source'      => 'test',
			'external_id'          => 'test-' . time() . '-' . wp_rand(),
			'certpsu'              => array(
				'organization_id'            => 'org-1',
				'certificate_email_template' => 'email-1',
				'endorser_required_endorsement_email_template' => 'email-2',
				'endorser_without_endorsement_email_template' => 'email-3',
			),
			'class'                => array(
				'name'         => 'Test Class',
				'printed_name' => 'Test Class',
				'started_date' => '2026-06-01',
				'ended_date'   => '2026-06-01',
				'issued_date'  => '2026-06-01',
			),
			'certificate_template' => array(
				'name'     => 'Cert',
				'template' => 'template-1',
			),
			'participants'         => array(
				array(
					'name'  => 'Alice',
					'email' => 'alice@example.com',
				),
			),
		);
	}

	/**
	 * Test fail_if_exists returns WP_Error.
	 *
	 * @return void
	 */
	public function test_fail_if_exists_returns_wp_error(): void {
		$payload = $this->valid_payload();
		$first   = certpsu()->create_issuance( $payload );
		self::assertFalse( is_wp_error( $first ) );

		$second_payload = array_merge( $payload, array( 'idempotency_mode' => 'fail_if_exists' ) );
		$second         = certpsu()->create_issuance( $second_payload );
		self::assertTrue( is_wp_error( $second ) );
		if ( is_wp_error( $second ) ) {
			self::assertSame( 'certpsu_issuance_exists', $second->get_error_code() );
		}
	}
}
