<?php
declare(strict_types=1);
namespace CertPSU\Connector\Tests\Unit\TutorLMS;

final class IndependentStateTest extends \PHPUnit\Framework\TestCase {
	public function test_completion_writes_independent_record_without_replacing_legacy_map(): void {
		$GLOBALS['mock_user_meta'][999]['_certpsu_tutorlms_issued'] = array( 2 => array( 'released' => true ) );
		$handler = new \CertPSU\TutorLMS\Issuance\Completion_Handler();
		$m       = new \ReflectionMethod( $handler, 'remember' );
		$m->invoke( $handler, 999, 1, 'class-a', 'participant-a', true, 'https://example.org/c' );
		self::assertSame( array( 2 => array( 'released' => true ) ), get_user_meta( 999, '_certpsu_tutorlms_issued', true ) );
		self::assertSame( 'participant-a', get_option( 'certpsu_learner_999_1' )['participant_id'] );
	}
}
