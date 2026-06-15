<?php
/**
 * Retry Failed Step Service Test.
 *
 * @package CertPSU\Connector\Tests\Unit\Application
 */

declare(strict_types=1);

namespace CertPSU\Connector\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use CertPSU\Connector\Application\Retry_Failed_Step_Service;

/**
 * Tests for Retry Failed Step Service.
 */
final class RetryFailedStepServiceTest extends TestCase {

	/**
	 * Test retry mapping.
	 *
	 * @return void
	 */
	public function test_retry_failed_step_maps_correctly(): void {
		$issuances = $this->createMock( \CertPSU\Connector\Database\Repositories\Issuance_Repository::class );
		$queue     = $this->createMock( \CertPSU\Connector\Queue\Queue::class );

		$issuances->expects( self::exactly( 3 ) )
			->method( 'find_by_id' )
			->willReturnOnConsecutiveCalls(
				array( 'failed_step' => 'release' ),
				array( 'failed_step' => 'poll_urls' ),
				array( 'failed_step' => 'create_class' )
			);

		$queue->expects( self::once() )->method( 'enqueue_release_issuance' )->with( 1 )->willReturn( true );
		$queue->expects( self::once() )->method( 'enqueue_refetch_certificate_urls' )->with( 2 )->willReturn( true );
		$queue->expects( self::once() )->method( 'enqueue_process_issuance' )->with( 3 )->willReturn( true );

		$service = new Retry_Failed_Step_Service( $issuances, $queue );

		$service->handle( 1 );
		$service->handle( 2 );
		$service->handle( 3 );
	}
}
