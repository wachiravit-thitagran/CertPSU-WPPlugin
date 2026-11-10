<?php
declare(strict_types=1);
namespace CertPSU\Connector\Tests\Unit\Application;

final class QueueFailureTest extends \PHPUnit\Framework\TestCase {
	public function test_zero_async_action_ids_are_failures(): void {
		$GLOBALS['mock_async_result'] = 0;
		$q                            = new \CertPSU\Connector\Queue\Queue();
		try {
			foreach ( array( 'enqueue_process_issuance', 'enqueue_release_issuance', 'enqueue_retry_failed_step', 'enqueue_refetch_certificate_urls' ) as $m ) {
					self::assertFalse( $q->$m( 1 ), $m ); }
		} finally {
			unset( $GLOBALS['mock_async_result'] ); }
	}
	public function test_failed_release_enqueue_persists_recoverable_failure(): void {
		$repo = $this->createMock( \CertPSU\Connector\Database\Repositories\Issuance_Repository::class );
		$repo->method( 'acquire_worker_claim' )->willReturn( true );
		$repo->method( 'find_by_id' )->willReturn(
			array(
				'status'           => 'processing',
				'current_step'     => 'add_participants',
				'certpsu_class_id' => 'A',
				'auto_release'     => 1,
			)
		);
		$repo->expects( self::once() )->method( 'mark_failed' )->with( 1, 'release', 'queue_failed', self::anything() );
		$q = $this->createMock( \CertPSU\Connector\Queue\Queue::class );
		$q->method( 'enqueue_release_issuance' )->willReturn( false );
		$service = new \CertPSU\Connector\Application\Process_Issuance_Workflow_Service( $repo, $this->createMock( \CertPSU\Connector\Database\Repositories\Certificate_Repository::class ), $this->createMock( \CertPSU\Connector\CertPSU\CertPSU_Api_Client::class ), $q );
		$service->handle( 1 );
	}
}
