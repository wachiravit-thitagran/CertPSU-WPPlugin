<?php
declare(strict_types=1);
namespace CertPSU\Connector\Tests\Unit\Connector;

final class CertificateReadinessTest extends \PHPUnit\Framework\TestCase {
	private $previous;
	protected function setUp(): void {
		$this->previous  = $GLOBALS['wpdb'];
		$GLOBALS['wpdb'] = new class() {
			public string $prefix     = 'wp_';
			public string $last_error = '';
			public bool $fail         = false;
			public array $rows        = array(
				array(
					'id'                     => 1,
					'email'                  => 'a@example.org',
					'certificate_url'        => '',
					'certpsu_certificate_id' => '',
					'status'                 => 'pending',
				),
				array(
					'id'                     => 2,
					'email'                  => 'b@example.org',
					'certificate_url'        => '',
					'certpsu_certificate_id' => '',
					'status'                 => 'pending',
				),
			);
			public function prepare( $q, ...$args ) {
				return $q;}
			public function get_results( $q, $format ) {
				return $this->rows;}
			public function update( $table, $data, $where ) {
				if ( $this->fail ) {
					$this->last_error = 'injected';
					return false;}
				foreach ( $this->rows as &$r ) {
					if ( str_ends_with( $table, 'certificates' ) && ( ( $where['email'] ?? null ) === $r['email'] || ( $where['id'] ?? null ) === $r['id'] ) ) {
								$r = array_merge( $r, $data );}
				}
				return 1;
			}
		};
	}
	protected function tearDown(): void {
		$GLOBALS['wpdb'] = $this->previous;}
	public function test_empty_response_preserves_expected_participant_count(): void {
		$s = ( new \CertPSU\Connector\Database\Repositories\Certificate_Repository() )->sync_urls_from_certpsu( 1, array() );
		self::assertSame( 2, $s['participant_count'] );
		self::assertSame( 0, $s['ready_count'] );
	}
	public function test_partial_response_and_replay_count_persisted_rows(): void {
		$r = new \CertPSU\Connector\Database\Repositories\Certificate_Repository();
		$p = array(
			array(
				'email'           => 'a@example.org',
				'certificate_id'  => 'c1',
				'certificate_url' => 'https://example.org/c1',
			),
		);
		$s = $r->sync_urls_from_certpsu( 1, $p );
		self::assertSame( 2, $s['participant_count'] );
		self::assertSame( 1, $s['ready_count'] );
		$s = $r->sync_urls_from_certpsu( 1, $p );
		self::assertSame( 1, $s['ready_count'] );
	}
	public function test_unknown_email_is_explicit_error(): void {
		$s = ( new \CertPSU\Connector\Database\Repositories\Certificate_Repository() )->sync_urls_from_certpsu(
			1,
			array(
				array(
					'email'           => 'unknown@example.org',
					'certificate_id'  => 'x',
					'certificate_url' => 'https://example.org/x',
				),
			)
		);
		self::assertInstanceOf( \WP_Error::class, $s );
	}
	public function test_failed_update_never_counts_response_as_ready(): void {
		$GLOBALS['wpdb']->fail = true;
		$s                     = ( new \CertPSU\Connector\Database\Repositories\Certificate_Repository() )->sync_urls_from_certpsu(
			1,
			array(
				array(
					'email'           => 'a@example.org',
					'certificate_id'  => 'x',
					'certificate_url' => 'https://example.org/x',
				),
			)
		);
		self::assertInstanceOf( \WP_Error::class, $s );
	}
}
