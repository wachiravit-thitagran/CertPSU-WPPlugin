<?php
declare(strict_types=1);

namespace CertPSU\Connector\Tests\Unit\Application;

use CertPSU\Connector\Application\Process_Issuance_Workflow_Service;
use CertPSU\Connector\Application\Release_Issuance_Service;
use CertPSU\Connector\Application\Retry_Failed_Step_Service;
use CertPSU\Connector\CertPSU\Api_Response;
use CertPSU\Connector\CertPSU\CertPSU_Api_Client;
use CertPSU\Connector\Database\Repositories\Certificate_Repository;
use CertPSU\Connector\Database\Repositories\Issuance_Repository;
use CertPSU\Connector\Queue\Queue;
use PHPUnit\Framework\TestCase;

/** Stateful persistence boundary: services and transitions under test are real. */
final class RecoveryRepository extends Issuance_Repository {
	public array $row = array( 'status' => 'queued', 'current_step' => 'queued', 'auto_release' => 0, 'certpsu_config_json' => '{}', 'class_payload_json' => '{}', 'template_payload_json' => '{}' );
	public bool $claimed = false;
	public bool $persist = true;
	public int $claim_count = 0;
	public function find_by_id( int $id ): ?array { return $this->row; }
	public function acquire_worker_claim( int $id ): bool {
		++$this->claim_count;
		if ( $this->claimed ) { return false; }
		return $this->claimed = true;
	}
	public function release_worker_claim( int $id ): void { $this->claimed = false; }
	public function save_workflow_checkpoint( int $id, array $data ): bool {
		if ( ! $this->persist ) { return false; }
		$this->row = array_merge( $this->row, $data );
		return true;
	}
	public function mark_failed( int $id, string $step, ?string $code, ?string $message ): void {
		$this->row = array_merge( $this->row, array( 'status' => 'failed', 'failed_step' => $step, 'last_error_code' => $code, 'last_error_message' => $message ) );
	}
	public function mark_class_created( int $id, string $class_id ): void { $this->row['certpsu_class_id'] = $class_id; }
	public function mark_participants_added( int $id ): void { $this->row['current_step'] = 'add_participants'; }
	public function mark_waiting_for_release( int $id ): void { $this->row['status'] = 'waiting_for_release'; }
	public function mark_released( int $id ): void { $this->row['status'] = 'released'; }
}

final class RecoveryQueue extends Queue {
	public array $deferred = array();
	public $on_release;
	public function enqueue_release_issuance( int $id ): bool { ( $this->on_release )(); return true; }
	public function schedule_release_issuance( int $id, int $delay ): bool { $this->deferred[] = array( 'release', $id, $delay ); return true; }
	public function schedule_process_issuance( int $id, int $delay ): bool { $this->deferred[] = array( 'process', $id, $delay ); return true; }
	public function schedule_poll_certificate_urls( int $id, int $delay ): bool { $this->deferred[] = array( 'poll', $id, $delay ); return true; }
}

final class WorkflowRecoveryTest extends TestCase {
	private RecoveryRepository $repo;
	private CertPSU_Api_Client $client;
	private Certificate_Repository $certificates;
	private Queue $queue;
	protected function setUp(): void {
		$this->repo = new RecoveryRepository();
		$this->client = $this->createMock( CertPSU_Api_Client::class );
		$this->certificates = $this->createMock( Certificate_Repository::class );
		$this->certificates->method( 'build_participants_payload' )->willReturn( array() );
		$this->queue = $this->createMock( Queue::class );
	}
	private function process(): Process_Issuance_Workflow_Service {
		return new Process_Issuance_Workflow_Service( $this->repo, $this->certificates, $this->client, $this->queue );
	}
	private function response( bool $success = true, ?int $code = 201, ?array $data = array() ): Api_Response {
		return new Api_Response( $success, $code, $data, null, $success ? null : 'transport_error', $success ? null : 'Timed out', null );
	}
	public function test_retry_participants_reuses_persisted_class(): void {
		$this->repo->row = array_merge( $this->repo->row, array( 'status' => 'failed', 'failed_step' => 'add_participants', 'certpsu_class_id' => 'existing', 'current_step' => 'create_class' ) );
		$this->client->expects( self::never() )->method( 'create_class' );
		$this->client->expects( self::once() )->method( 'add_participants' )->with( 'existing', array( 'participants' => array() ) )->willReturn( $this->response() );
		$this->process()->handle( 1 );
		self::assertSame( 'waiting_for_release', $this->repo->row['status'] );
		self::assertNull( $this->repo->row['failed_step'] );
		self::assertFalse( $this->repo->claimed );
	}
	public function test_duplicate_process_of_waiting_or_terminal_issuance_does_nothing(): void {
		$this->client->expects( self::never() )->method( 'create_class' );
		$this->client->expects( self::never() )->method( 'add_participants' );
		foreach ( array( 'waiting_for_release', 'released', 'completed', 'partially_completed' ) as $status ) {
			$this->repo->row['status'] = $status;
			$this->process()->handle( 1 );
			self::assertSame( $status, $this->repo->row['status'] );
		}
	}
	public function test_competing_worker_cannot_call_remote_api(): void {
		$this->repo->claimed = true;
		$this->client->expects( self::never() )->method( 'create_class' );
		// Contention must retain delivery instead of consuming the only job.
		$this->queue->expects( self::once() )->method( 'schedule_process_issuance' )->with( 1, 30 )->willReturn( true );
		$this->process()->handle( 1 );
		self::assertTrue( $this->repo->claimed );
		self::assertSame( 1, $this->repo->claim_count );
	}
	public function test_timeout_is_visible_and_not_replayed(): void {
		$this->client->expects( self::once() )->method( 'create_class' )->willReturn( $this->response( false, null, null ) );
		$this->process()->handle( 1 );
		self::assertSame( 'remote_outcome_unknown', $this->repo->row['last_error_code'] );
		self::assertStringContainsString( 'reconcile', $this->repo->row['last_error_message'] );
		$this->process()->handle( 1 );
		$this->queue->expects( self::never() )->method( 'enqueue_process_issuance' );
		( new Retry_Failed_Step_Service( $this->repo, $this->queue ) )->handle( 1 );
	}
	public function test_crash_after_remote_acceptance_leaves_unknown_checkpoint(): void {
		$this->client->expects( self::once() )->method( 'create_class' )->willReturnCallback( function () {
			self::assertSame( 'remote_outcome_unknown', $this->repo->row['last_error_code'] ?? null );
			throw new \RuntimeException( 'Connection died after remote acceptance' );
		} );
		try { $this->process()->handle( 1 ); } catch ( \RuntimeException $e ) {}
		self::assertSame( 'remote_outcome_unknown', $this->repo->row['last_error_code'] );
		self::assertFalse( $this->repo->claimed );
		$this->process()->handle( 1 );
	}
	public function test_persistence_failure_prevents_remote_creation(): void {
		$this->repo->persist = false;
		$this->client->expects( self::never() )->method( 'create_class' );
		$this->process()->handle( 1 );
		self::assertFalse( $this->repo->claimed );
	}
	public function test_empty_successful_class_id_requires_reconciliation(): void {
		$this->client->method( 'create_class' )->willReturn( $this->response() );
		$this->client->expects( self::never() )->method( 'add_participants' );
		$this->process()->handle( 1 );
		self::assertSame( 'remote_outcome_unknown', $this->repo->row['last_error_code'] );
	}
	public function test_duplicate_release_is_not_sent_twice(): void {
		$this->repo->row = array_merge( $this->repo->row, array( 'status' => 'waiting_for_release', 'certpsu_class_id' => 'existing', 'current_step' => 'waiting_for_release' ) );
		$this->client->expects( self::once() )->method( 'release_participants' )->willReturn( $this->response( true, 202 ) );
		$this->queue->expects( self::once() )->method( 'schedule_poll_certificate_urls' )->willReturn( true );
		$service = new Release_Issuance_Service( $this->repo, $this->certificates, $this->client, $this->queue );
		$service->handle( 1 );
		self::assertSame( 'released', $this->repo->row['status'] );
		$service->handle( 1 );
	}
	public function test_release_requires_participant_checkpoint(): void {
		$this->repo->row['certpsu_class_id'] = 'existing';
		$this->client->expects( self::never() )->method( 'release_participants' );
		( new Release_Issuance_Service( $this->repo, $this->certificates, $this->client, $this->queue ) )->handle( 1 );
	}
	public function test_successful_participant_checkpoint_skips_readding_on_duplicate(): void {
		$this->repo->row = array_merge( $this->repo->row, array( 'status' => 'processing', 'current_step' => 'add_participants', 'certpsu_class_id' => 'existing', 'auto_release' => 1 ) );
		$this->client->expects( self::never() )->method( 'create_class' );
		$this->client->expects( self::never() )->method( 'add_participants' );
		$this->queue->expects( self::once() )->method( 'enqueue_release_issuance' )->willReturn( true );
		$this->process()->handle( 1 );
	}
	public function test_poll_does_not_mutate_terminal_or_claimed_issuances(): void {
		$this->repo->row['certpsu_class_id'] = 'existing';
		$this->client->expects( self::never() )->method( 'get_participants' );
		$normalizer = $this->createMock( \CertPSU\Connector\CertPSU\Participant_Response_Normalizer::class );
		$service = new \CertPSU\Connector\Application\Refetch_Certificate_Urls_Service( $this->repo, $this->certificates, $this->client, $normalizer, $this->queue );
		foreach ( array( 'completed', 'partially_completed', 'waiting_for_release' ) as $status ) {
			$this->repo->row['status'] = $status;
			$service->handle( 1 );
		}
		$this->repo->row['status'] = 'released';
		$this->repo->claimed = true;
		$this->queue->expects( self::once() )->method( 'schedule_poll_certificate_urls' )->with( 1, 30 )->willReturn( true );
		$service->handle( 1 );
	}
	public function test_release_delivery_during_process_claim_is_deferred_then_succeeds(): void {
		$this->repo->row['auto_release'] = 1;
		$this->client->expects( self::once() )->method( 'create_class' )->willReturn( $this->response( true, 201, array( 'id' => 'new-class' ) ) );
		$this->client->expects( self::once() )->method( 'add_participants' )->willReturn( $this->response() );
		$this->client->expects( self::once() )->method( 'release_participants' )->willReturn( $this->response( true, 202 ) );
		$queue = new RecoveryQueue();
		$release = new Release_Issuance_Service( $this->repo, $this->certificates, $this->client, $queue );
		$queue->on_release = static function () use ( $release ) { $release->handle( 1 ); };
		( new Process_Issuance_Workflow_Service( $this->repo, $this->certificates, $this->client, $queue ) )->handle( 1 );
		self::assertSame( array( array( 'release', 1, 30 ) ), $queue->deferred );
		self::assertSame( 'processing', $this->repo->row['status'] );
		$release->handle( 1 );
		self::assertSame( 'released', $this->repo->row['status'] );
		self::assertSame( array( 'poll', 1, 60 ), $queue->deferred[1] );
	}
	public function test_process_delivery_is_deferred_on_contention(): void {
		$this->repo->claimed = true;
		$queue = new RecoveryQueue();
		$this->client->expects( self::never() )->method( 'create_class' );
		( new Process_Issuance_Workflow_Service( $this->repo, $this->certificates, $this->client, $queue ) )->handle( 1 );
		self::assertSame( array( array( 'process', 1, 30 ) ), $queue->deferred );
	}
	public function test_exhausted_terminal_poll_failure_cannot_be_replayed(): void {
		$this->repo->row = array_merge( $this->repo->row, array( 'certpsu_class_id' => 'existing', 'status' => 'failed', 'failed_step' => 'poll_urls', 'current_step' => 'poll_urls', 'poll_attempt_count' => 60 ) );
		$this->client->expects( self::never() )->method( 'get_participants' );
		$this->queue->expects( self::never() )->method( 'enqueue_refetch_certificate_urls' );
		( new Retry_Failed_Step_Service( $this->repo, $this->queue ) )->handle( 1 );
		$normalizer = $this->createMock( \CertPSU\Connector\CertPSU\Participant_Response_Normalizer::class );
		( new \CertPSU\Connector\Application\Refetch_Certificate_Urls_Service( $this->repo, $this->certificates, $this->client, $normalizer, $this->queue ) )->handle( 1 );
	}
}
