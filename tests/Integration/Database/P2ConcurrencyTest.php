<?php
declare(strict_types=1);
namespace CertPSU\Connector\Tests\Integration\Database;

use PHPUnit\Framework\TestCase;
use CertPSU\Connector\Database\Repositories\Issuance_Repository;
use CertPSU\Connector\Application\Create_Issuance_Service;
use CertPSU\Connector\Database\Repositories\Certificate_Repository;
use CertPSU\Connector\Support\Settings;
use CertPSU\Connector\Support\Validator;
use CertPSU\Connector\Queue\Queue;
final class P2ConcurrencyTest extends TestCase {
	private const WORKER = __DIR__ . '/../../fixtures/p2-concurrency-worker.php';
	protected function setUp(): void {
		if ( ! function_exists( 'dbDelta' ) || ! file_exists( (string) getenv( 'CERTPSU_WP_TEST_CONFIG' ) ) ) {
			self::markTestSkipped( 'Local real WP/MySQL concurrency fixture required.' );}}
	private function start( array $input ): array {
		$dir = sys_get_temp_dir() . '/certpsu-p2-' . wp_generate_uuid4();
		mkdir( $dir );
		$input += array(
			'ready'  => $dir . '/ready',
			'output' => $dir . '/output',
			'go'     => $dir . '/go',
		);
		file_put_contents( $dir . '/input', json_encode( $input ) );
		$process = proc_open(
			array( PHP_BINARY, self::WORKER, $dir . '/input' ),
			array(
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			),
			$pipes
		);
		return array( $process, $pipes, $input );
	}
	private function waitReady( array $worker ): void {
		$deadline = microtime( true ) + 8;
		while ( ! file_exists( $worker[2]['ready'] ) && microtime( true ) < $deadline ) {
			usleep( 10000 );}
		self::assertFileExists( $worker[2]['ready'] );
	}
	private function finish( array $worker ): array {
		$out = stream_get_contents( $worker[1][1] );
		$err = stream_get_contents( $worker[1][2] );
		fclose( $worker[1][1] );
		fclose( $worker[1][2] );
		self::assertSame( 0, proc_close( $worker[0] ), $out . $err );
		self::assertFileExists( $worker[2]['output'], $out . $err );
		return json_decode( file_get_contents( $worker[2]['output'] ), true );
	}
	private function args( string $mode, string $ref ): array {
		return array(
			'external_source'      => 'p2-concurrent',
			'external_id'          => $ref,
			'idempotency_mode'     => $mode,
			'certpsu'              => array(
				'organization_id'            => 'org',
				'certificate_email_template' => 'email',
				'endorser_required_endorsement_email_template' => 'email',
				'endorser_without_endorsement_email_template' => 'email',
			),
			'class'                => array(
				'name'                        => 'P2',
				'printed_name'                => 'P2',
				'started_date'                => '2026-11-09',
				'ended_date'                  => '2026-11-09',
				'issued_date'                 => '2026-11-09',
				'allow_duplicate_participant' => 'not_allowed',
				'auto_send_mail_participant'  => 'auto',
				'endorse_method'              => 'auto',
			),
			'certificate_template' => array(
				'name'     => 'C',
				'group'    => 'participant',
				'template' => 'template',
			),
			'participants'         => array(
				array(
					'name'  => 'A',
					'email' => 'a@example.test',
				),
			),
		);
	}
	public function test_two_real_connections_honor_all_idempotency_modes(): void {
		global $wpdb;
		foreach ( array( 'return_existing', 'fail_if_exists', 'always_create' ) as $mode ) {
			$ref           = wp_generate_uuid4();
			$args          = $this->args( $mode, strtoupper( $ref ) );
			$worker        = null;
			$repo          = new class() extends Issuance_Repository {
				public $barrier;
				public function find_latest_by_external_ref( string $source, string $id ): ?array {
					( $this->barrier )();
					return parent::find_latest_by_external_ref( $source, $id );}
			};
			$repo->barrier = function () use ( $args, &$worker ) {
				$args['external_id'] = strtolower( $args['external_id'] );
				$worker = $this->start(
					array(
						'kind' => 'create',
						'args' => $args,
					)
				);
				$this->waitReady( $worker );
				usleep( 150000 );
				self::assertFileDoesNotExist( $worker[2]['output'], 'second request waits for first external-reference claim' );
			};
			$service       = new Create_Issuance_Service( new Validator(), $repo, new Certificate_Repository(), new Queue(), new Settings() );
			$first         = $service->handle( $args );
			self::assertNotInstanceOf( \WP_Error::class, $first );
			$second = $this->finish( $worker );
			$count  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}certpsu_issuances WHERE external_source=%s AND external_id=%s", 'p2-concurrent', $ref ) );
			self::assertSame( $mode === 'always_create' ? 2 : 1, $count, $mode );
			if ( $mode === 'return_existing' ) {
				self::assertSame( $first->issuance_id, $second['id'] );
				self::assertTrue( $second['existing'] );
			} elseif ( $mode === 'fail_if_exists' ) {
				self::assertSame( 'certpsu_issuance_exists', $second['error'] );
			} else {
				self::assertNotSame( $first->issuance_id, $second['id'] );}
		}
	}
	public function test_parallel_course_state_writes_survive_shared_initial_read(): void {
		$u = wp_create_user( 'parallel-' . wp_generate_uuid4(), wp_generate_password(), 'parallel-' . wp_generate_uuid4() . '@example.test' );
		$a = $this->start(
			array(
				'kind'   => 'state',
				'user'   => $u,
				'course' => 901,
				'data'   => array(
					'class_id'        => 'a',
					'participant_id'  => 'pa',
					'released'        => true,
					'certificate_url' => 'https://example.test/a',
				),
			)
		);
		$b = $this->start(
			array(
				'kind'   => 'state',
				'user'   => $u,
				'course' => 902,
				'data'   => array(
					'class_id'        => 'b',
					'participant_id'  => 'pb',
					'released'        => false,
					'certificate_url' => '',
				),
			)
		);
		$this->waitReady( $a );
		$this->waitReady( $b );
		touch( $a[2]['go'] );
		touch( $b[2]['go'] );
		self::assertTrue( $this->finish( $a )['saved'] );
		self::assertTrue( $this->finish( $b )['saved'] );
		$r = new \CertPSU\TutorLMS\Issuance\Learner_State_Repository();
		self::assertSame( 'pa', $r->get( $u, 901 )['participant_id'] );
		self::assertSame( 'pb', $r->get( $u, 902 )['participant_id'] );
	}
	public function test_course_claim_excludes_second_remote_create_and_recovers_local_persist_failure(): void {
		global $wpdb;
		$course       = wp_insert_post(
			array(
				'post_type'   => 'courses',
				'post_status' => 'publish',
				'post_title'  => 'Concurrent class',
			)
		);
		$calls        = 0;
		$workerResult = null;
		$http         = function ( $pre, $args, $url ) use ( $course, &$calls, &$workerResult ) {
			if ( str_ends_with( $url, '/v2/classes' ) ) {
				++$calls;
				$worker       = $this->start(
					array(
						'kind'   => 'course',
						'course' => $course,
					)
				);
				$workerResult = $this->finish( $worker );
				return array(
					'headers'  => array(),
					'body'     => json_encode(
						array(
							'data' => array( 'id' => 'one-class' ),
							'meta' => array( 'request_id' => 'fixture' ),
						)
					),
					'response' => array(
						'code'    => 201,
						'message' => 'Created',
					),
				);
			}return $pre;
		};
		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', $http, 10, 3 );
		$break = function ( $check, $object, $key ) use ( $course ) {
			return $object === $course && $key === '_certpsu_class_id' ? false : $check;
		};
		add_filter( 'update_post_metadata', $break, 10, 3 );
		try {
			$first = ( new \CertPSU\TutorLMS\Issuance\Course_Class_Manager() )->ensure_class( $course, array() );
		} finally {
			remove_filter( 'update_post_metadata', $break );
			remove_filter( 'pre_http_request', $http );
			add_filter( 'pre_http_request', static fn()=>new \WP_Error( 'offline_only', 'External HTTP blocked' ), -999 );}
		self::assertSame( 'certpsu_class_locked', $workerResult['error'] );
		self::assertInstanceOf( \WP_Error::class, $first );
		self::assertSame( 'certpsu_class_persist_failed', $first->get_error_code() );
		$manager = new \CertPSU\TutorLMS\Issuance\Course_Class_Manager();
		self::assertSame( 'one-class', $manager->ensure_class( $course, array() ) );
		self::assertSame( 1, $calls );
		self::assertSame(
			'one-class',
			$this->finish(
				$this->start(
					array(
						'kind'   => 'course',
						'course' => $course,
					)
				)
			)['id']
		);
	}
}
