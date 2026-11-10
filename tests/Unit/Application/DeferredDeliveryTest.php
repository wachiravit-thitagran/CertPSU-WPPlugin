<?php
declare(strict_types=1);
namespace CertPSU\Connector\Tests\Unit\Application;

final class DeferredDeliveryTest extends \PHPUnit\Framework\TestCase {
	protected function tearDown(): void { unset( $GLOBALS['mock_schedule_result'] ); }
	public function test_zero_action_id_is_not_successful_deferral(): void {
		$GLOBALS['mock_schedule_result'] = 0;
		$queue = new \CertPSU\Connector\Queue\Queue();
		self::assertFalse( $queue->schedule_process_issuance( 1, 30 ) );
		self::assertFalse( $queue->schedule_release_issuance( 1, 30 ) );
		self::assertFalse( $queue->schedule_poll_certificate_urls( 1, 30 ) );
	}
	public function test_positive_action_id_retains_deferred_delivery(): void {
		$GLOBALS['mock_schedule_result'] = 124;
		$queue = new \CertPSU\Connector\Queue\Queue();
		self::assertTrue( $queue->schedule_process_issuance( 1, 30 ) );
		self::assertTrue( $queue->schedule_release_issuance( 1, 30 ) );
		self::assertTrue( $queue->schedule_poll_certificate_urls( 1, 30 ) );
	}
}
