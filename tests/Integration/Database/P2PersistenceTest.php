<?php
declare(strict_types=1);
namespace CertPSU\Connector\Tests\Integration\Database;

use CertPSU\Connector\Database\Repositories\Certificate_Repository;
use CertPSU\Connector\Database\Repositories\Issuance_Repository;
use CertPSU\TutorLMS\Issuance\Learner_State_Repository;
use PHPUnit\Framework\TestCase;
final class P2PersistenceTest extends TestCase {
	protected function setUp(): void {
		if ( ! function_exists( 'dbDelta' ) ) {
			self::markTestSkipped( 'Real WordPress/MySQL fixture required.' );}}
	public function test_expected_local_rows_and_errors(): void {
		global $wpdb;
		$repo  = new Issuance_Repository();
		$id    = $repo->insert(
			array(
				'external_source'       => 'p2',
				'external_id'           => wp_generate_uuid4(),
				'idempotency_key'       => wp_generate_uuid4(),
				'idempotency_mode'      => 'always_create',
				'status'                => 'released',
				'current_step'          => 'release',
				'auto_release'          => 1,
				'certpsu_config_json'   => '{}',
				'class_payload_json'    => '{}',
				'template_payload_json' => '{}',
				'participant_count'     => 2,
			)
		);
		$certs = new Certificate_Repository();
		self::assertTrue(
			$certs->insert_many(
				$id,
				array(
					array(
						'name'  => 'A',
						'email' => 'a@example.test',
						'group' => 'participant',
					),
					array(
						'name'  => 'B',
						'email' => 'b@example.test',
						'group' => 'participant',
					),
				)
			)
		);
		self::assertSame(
			array(
				'ready_count'       => 0,
				'participant_count' => 2,
			),
			$certs->sync_urls_from_certpsu( $id, array() )
		);
		$one = array(
			array(
				'email'           => 'a@example.test',
				'certificate_id'  => 'A',
				'certificate_url' => 'https://example.test/A',
			),
		);
		self::assertSame(
			array(
				'ready_count'       => 1,
				'participant_count' => 2,
			),
			$certs->sync_urls_from_certpsu( $id, $one )
		);
		self::assertSame(
			array(
				'ready_count'       => 1,
				'participant_count' => 2,
			),
			$certs->sync_urls_from_certpsu( $id, $one )
		);
		self::assertInstanceOf(
			\WP_Error::class,
			$certs->sync_urls_from_certpsu(
				$id,
				array(
					array(
						'email'           => 'other@example.test',
						'certificate_id'  => 'X',
						'certificate_url' => 'https://example.test/X',
					),
				)
			)
		);
		$broken = static fn( $sql )=>str_starts_with( $sql, "UPDATE `{$wpdb->prefix}certpsu_certificates`" ) ? 'UPDATE missing_p2_test_table SET missing=1' : $sql;
		$wpdb->suppress_errors( true );
		add_filter( 'query', $broken );
		try {
			self::assertInstanceOf(
				\WP_Error::class,
				$certs->sync_urls_from_certpsu(
					$id,
					array(
						array(
							'email'           => 'b@example.test',
							'certificate_id'  => 'B',
							'certificate_url' => 'https://example.test/B',
						),
					)
				)
			);
		} finally {
			remove_filter( 'query', $broken );
			$wpdb->suppress_errors( false );}
		self::assertSame(
			array(
				'ready_count'       => 2,
				'participant_count' => 2,
			),
			$certs->sync_urls_from_certpsu(
				$id,
				array(
					array(
						'email'           => 'b@example.test',
						'certificate_id'  => 'B',
						'certificate_url' => 'https://example.test/B',
					),
				)
			)
		);
	}
	public function test_transition_enqueue_failure_is_recoverable_without_remote_replay(): void {
		$repo = new Issuance_Repository();
		$id   = $repo->insert(
			array(
				'external_source'       => 'p2-queue',
				'external_id'           => wp_generate_uuid4(),
				'idempotency_key'       => wp_generate_uuid4(),
				'idempotency_mode'      => 'always_create',
				'status'                => 'processing',
				'current_step'          => 'add_participants',
				'certpsu_class_id'      => 'queue-class',
				'auto_release'          => 1,
				'certpsu_config_json'   => '{}',
				'class_payload_json'    => '{}',
				'template_payload_json' => '{}',
				'participant_count'     => 1,
			)
		);
		$zero = static fn()=>0;
		add_filter( 'pre_as_enqueue_async_action', $zero );
		try {
			certpsu()->container()->get( 'process_issuance_workflow_service' )->handle( $id );
		} finally {
			remove_filter( 'pre_as_enqueue_async_action', $zero );}
		$row = $repo->find_by_id( $id );
		self::assertSame( 'failed', $row['status'] );
		self::assertSame( 'release', $row['failed_step'] );
		self::assertSame( 'queue_failed', $row['last_error_code'] );
		self::assertSame( 'add_participants', $row['current_step'] );
		$calls = 0;
		$http  = static function () use ( &$calls ) {
			++$calls;
			return array(
				'headers'  => array(),
				'body'     => json_encode(
					array(
						'data' => array(),
						'meta' => array( 'request_id' => 'queue-fixture' ),
					)
				),
				'response' => array(
					'code'    => 202,
					'message' => 'Accepted',
				),
			);
		};
		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', $http );
		add_filter( 'pre_as_schedule_single_action', $zero );
		try {
			certpsu()->container()->get( 'release_issuance_service' )->handle( $id );
		} finally {
			remove_filter( 'pre_as_schedule_single_action', $zero );
			remove_filter( 'pre_http_request', $http );
			add_filter( 'pre_http_request', static fn()=>new \WP_Error( 'offline_only', 'External HTTP blocked' ), -999 );}
		$row = $repo->find_by_id( $id );
		self::assertSame( 1, $calls );
		self::assertSame( 'poll_urls', $row['failed_step'] );
		self::assertSame( 'release', $row['current_step'] );
		self::assertSame( 'queue_failed', $row['last_error_code'] );
		certpsu()->container()->get( 'retry_failed_step_service' )->handle( $id );
		self::assertSame( 1, $calls, 'retry schedules poll rather than releasing again' );
	}
	public function test_independent_states_monotonic_updates_and_safe_legacy_import(): void {
		$u = wp_create_user( 'p2-' . wp_generate_uuid4(), wp_generate_password(), 'p2-' . wp_generate_uuid4() . '@example.test' );
		$a = wp_insert_post(
			array(
				'post_type'   => 'courses',
				'post_status' => 'publish',
				'post_title'  => 'A',
			)
		);
		$b = wp_insert_post(
			array(
				'post_type'   => 'courses',
				'post_status' => 'publish',
				'post_title'  => 'B',
			)
		);
		update_post_meta( $a, '_certpsu_class_id', 'class-a' );
		update_post_meta( $b, '_certpsu_class_id', 'class-b' );
		$r = new Learner_State_Repository();
		self::assertTrue(
			$r->save(
				$u,
				$a,
				array(
					'class_id'        => 'class-a',
					'participant_id'  => 'p-a',
					'released'        => true,
					'certificate_url' => 'https://example.test/A',
				)
			)
		);
		self::assertTrue(
			$r->save(
				$u,
				$b,
				array(
					'class_id'       => 'class-b',
					'participant_id' => 'p-b',
					'released'       => false,
				)
			)
		);
		self::assertTrue(
			$r->save(
				$u,
				$a,
				array(
					'released'        => false,
					'certificate_url' => '',
				)
			)
		);
		self::assertTrue( $r->get( $u, $a )['released'] );
		self::assertSame( 'p-b', $r->get( $u, $b )['participant_id'] );
		self::assertSame( 'https://example.test/A', $r->get( $u, $a )['certificate_url'] );
		self::assertCount( 2, $r->for_user( $u ) );
		self::assertFalse(
			$r->save(
				$u,
				$a,
				array(
					'class_id' => 'stale-class',
					'released' => true,
				)
			)
		);
		$legacy = array(
			'class_id'       => 'class-b',
			'participant_id' => 'legacy-b',
			'released'       => true,
		);
		delete_option( 'certpsu_learner_' . $u . '_' . $b );
		update_user_meta(
			$u,
			'_certpsu_tutorlms_issued',
			array(
				$a => array(
					'class_id' => 'foreign',
					'released' => true,
				),
				$b => $legacy,
			)
		);
		self::assertSame( $legacy, $r->get( $u, $b ) );
		delete_option( 'certpsu_learner_' . $u . '_' . $a );
		self::assertSame( array(), $r->get( $u, $a ) );
		self::assertArrayHasKey( $a, get_user_meta( $u, '_certpsu_tutorlms_issued', true ) );
	}
	public function test_legacy_migration_insert_failure_blocks_replay(): void {
		global $wpdb;
		$u = wp_create_user( 'legacy-fail-' . wp_generate_uuid4(), wp_generate_password(), 'legacy-fail-' . wp_generate_uuid4() . '@example.test' );
		$c = wp_insert_post( array( 'post_type' => 'courses', 'post_status' => 'publish', 'post_title' => 'Legacy insert failure' ) );
		update_post_meta( $c, '_certpsu_class_id', 'legacy-class' );
		update_user_meta( $u, '_certpsu_tutorlms_issued', array( $c => array( 'class_id' => 'legacy-class', 'participant_id' => 'legacy-participant', 'released' => true ) ) );
		$key = 'certpsu_learner_' . $u . '_' . $c;
		$broken = static fn( $sql ) => str_starts_with( $sql, "INSERT INTO `{$wpdb->options}`" ) && str_contains( $sql, $key ) ? 'INSERT INTO missing_p2_table VALUES (1)' : $sql;
		$wpdb->suppress_errors( true );
		add_filter( 'query', $broken );
		$caught = false;
		try {
			$handler = new \CertPSU\TutorLMS\Issuance\Completion_Handler();
			$check = new \ReflectionMethod( $handler, 'already_released' );
			$check->invoke( $handler, $u, $c );
		} catch ( \RuntimeException $error ) {
			$caught = true;
		} finally {
			remove_filter( 'query', $broken );
			$wpdb->suppress_errors( false );
		}
		self::assertTrue( $caught, 'Migration persistence failure must abort before participant/release replay.' );
		self::assertSame( 'legacy-participant', ( new Learner_State_Repository() )->get( $u, $c )['participant_id'] );
	}

	public function test_multisite_equal_numeric_course_ids_stay_independent(): void {
		if ( ! is_multisite() ) {
			self::markTestSkipped( 'Multisite fixture required.' );}
		$u     = wp_create_user( 'p2ms-' . wp_generate_uuid4(), wp_generate_password(), 'p2ms-' . wp_generate_uuid4() . '@example.test' );
		$siteA = get_current_blog_id();
		$siteB = wpmu_create_blog( 'second.example.org', '/', 'P2 second site', 1 );
		self::assertIsInt( $siteB );
		$course = wp_insert_post(
			array(
				'post_type'   => 'courses',
				'post_status' => 'publish',
				'post_title'  => 'A',
			)
		);
		update_post_meta( $course, '_certpsu_class_id', 'class-a' );
		$r = new Learner_State_Repository();
		self::assertTrue(
			$r->save(
				$u,
				$course,
				array(
					'class_id'        => 'class-a',
					'participant_id'  => 'part-a',
					'released'        => true,
					'certificate_url' => 'https://example.test/A',
				)
			)
		);
		update_user_meta(
			$u,
			'_certpsu_tutorlms_issued',
			array(
				$course => array(
					'class_id'       => 'class-a',
					'participant_id' => 'legacy-a',
					'released'       => true,
				),
			)
		);
		switch_to_blog( $siteB );
		try {
			global $wpdb;
			$wpdb->insert(
				$wpdb->posts,
				array(
					'ID'          => $course,
					'post_type'   => 'courses',
					'post_status' => 'publish',
					'post_title'  => 'B',
				)
			);
			update_post_meta( $course, '_certpsu_class_id', 'class-b' );
			self::assertSame( array(), $r->get( $u, $course ), 'foreign legacy must not be imported' );
			self::assertTrue(
				$r->save(
					$u,
					$course,
					array(
						'class_id'        => 'class-b',
						'participant_id'  => 'part-b',
						'released'        => false,
						'certificate_url' => '',
					)
				)
			);
			self::assertSame( 'part-b', $r->get( $u, $course )['participant_id'] );
			self::assertFalse( $r->get( $u, $course )['released'] );
		} finally {
			restore_current_blog();}
		self::assertSame( $siteA, get_current_blog_id() );
		self::assertSame( 'part-a', $r->get( $u, $course )['participant_id'] );
		self::assertTrue( $r->get( $u, $course )['released'] );
		delete_option( 'certpsu_learner_' . $u . '_' . $course );
		switch_to_blog( $siteB );
		update_post_meta( $course, '_certpsu_class_id', 'class-a' );
		restore_current_blog();
		self::assertSame( array(), $r->get( $u, $course ), 'ambiguous shared class legacy must remain quarantined' );
		$check = new \ReflectionMethod( \CertPSU\TutorLMS\Issuance\Completion_Handler::class, 'already_released' );
		$this->expectException( \RuntimeException::class );
		$check->invoke( new \CertPSU\TutorLMS\Issuance\Completion_Handler(), $u, $course );

	}
}
