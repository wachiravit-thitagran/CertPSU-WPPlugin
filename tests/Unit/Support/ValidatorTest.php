<?php
/**
 * Validator Test.
 *
 * @package CertPSU\Connector\Tests\Unit\Support
 */

declare(strict_types=1);

namespace CertPSU\Connector\Tests\Unit\Support;

use CertPSU\Connector\Support\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Tests validator.
 */
final class ValidatorTest extends TestCase {

	/**
	 * Ensure defaults are applied.
	 *
	 * @return void
	 */
	public function test_validator_defaults_instructors_and_participant_group(): void {
		$validator = new Validator();

		$validated = $validator->validate_create_issuance(
			array(
				'external_source'      => 'training-plugin',
				'external_id'          => 'course-1',
				'certpsu'              => array(
					'organization_id'            => 'org-1',
					'certificate_email_template' => 'email-1',
					'endorser_required_endorsement_email_template' => 'email-2',
					'endorser_without_endorsement_email_template' => 'email-3',
				),
				'class'                => array(
					'name'         => 'Class',
					'printed_name' => 'Class',
					'started_date' => '2026-06-01',
					'ended_date'   => '2026-06-01',
					'issued_date'  => '2026-06-01',
				),
				'certificate_template' => array(
					'name'     => 'Certificate',
					'template' => 'template-1',
				),
				'participants'         => array(
					array(
						'name'  => 'Alice',
						'email' => 'alice@example.com',
					),
				),
			)
		);

		self::assertIsArray( $validated );
		self::assertSame( array(), $validated['class']['instructors'] );
		self::assertSame( 'participant', $validated['participants'][0]['group'] );
	}

	/**
	 * Test idempotency mode validation.
	 *
	 * @return void
	 */
	public function test_validator_idempotency_mode(): void {
		$validator = new Validator();

		$base = array(
			'external_source'      => 'training-plugin',
			'external_id'          => 'course-1',
			'certpsu'              => array(
				'organization_id'            => 'org-1',
				'certificate_email_template' => 'email-1',
				'endorser_required_endorsement_email_template' => 'email-2',
				'endorser_without_endorsement_email_template' => 'email-3',
			),
			'class'                => array(
				'name'         => 'Class',
				'printed_name' => 'Class',
				'started_date' => '2026-06-01',
				'ended_date'   => '2026-06-01',
				'issued_date'  => '2026-06-01',
			),
			'certificate_template' => array(
				'name'     => 'Certificate',
				'template' => 'template-1',
			),
			'participants'         => array(
				array(
					'name'  => 'Alice',
					'email' => 'alice@example.com',
				),
			),
		);

		// Default is return_existing.
		$validated = $validator->validate_create_issuance( $base );
		self::assertSame( 'return_existing', $validated['idempotency_mode'] );

		// Set to fail_if_exists.
		$base['idempotency_mode'] = 'fail_if_exists';
		$validated2               = $validator->validate_create_issuance( $base );
		self::assertSame( 'fail_if_exists', $validated2['idempotency_mode'] );
	}
}
