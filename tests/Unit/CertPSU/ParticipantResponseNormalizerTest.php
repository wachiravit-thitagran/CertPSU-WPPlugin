<?php
/**
 * Participant Response Normalizer Test.
 *
 * @package CertPSU\Connector\Tests\Unit\CertPSU
 */

declare(strict_types=1);

namespace CertPSU\Connector\Tests\Unit\CertPSU;

use CertPSU\Connector\CertPSU\Participant_Response_Normalizer;
use PHPUnit\Framework\TestCase;

/**
 * Tests Participant_Response_Normalizer.
 */
final class ParticipantResponseNormalizerTest extends TestCase {

	/**
	 * Test normalizes object to list.
	 *
	 * @return void
	 */
	public function test_normalizes_object_map_to_list(): void {
		$normalizer = new Participant_Response_Normalizer();

		$payload = array(
			'participants' => array(
				'id-1' => array(
					'name'  => 'Alice',
					'email' => 'alice@example.com',
				),
				'id-2' => array(
					'id'    => 'custom-id',
					'name'  => 'Bob',
					'email' => 'bob@example.com',
				),
			),
		);

		$normalized = $normalizer->normalize( $payload );

		self::assertCount( 2, $normalized );
		self::assertSame( 'id-1', $normalized[0]['id'] );
		self::assertSame( 'Alice', $normalized[0]['name'] );
		self::assertSame( 'custom-id', $normalized[1]['id'] );
		// Email is preserved: URL sync reconciles rows by email under API v2.
		self::assertSame( 'alice@example.com', $normalized[0]['email'] );
		self::assertSame( 'bob@example.com', $normalized[1]['email'] );
	}
}
