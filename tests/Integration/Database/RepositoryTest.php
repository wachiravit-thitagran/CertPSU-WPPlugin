<?php
/**
 * Repository Test.
 *
 * @package CertPSU\Connector\Tests\Integration\Database
 */

declare(strict_types=1);

namespace CertPSU\Connector\Tests\Integration\Database;

use PHPUnit\Framework\TestCase;

/**
 * Tests repositories.
 */
final class RepositoryTest extends TestCase {

	/**
	 * Ensure we can insert and find issuances.
	 *
	 * @return void
	 */
	public function test_issuance_repository_inserts_and_finds_by_external_ref(): void {
		if ( ! function_exists( 'dbDelta' ) ) {
			self::markTestSkipped( 'Database tests require WordPress environment.' );
		}

		$repo = new \CertPSU\Connector\Database\Repositories\Issuance_Repository();

		$issuance_id = $repo->insert(
			array(
				'external_source'       => 'training-plugin',
				'external_id'           => 'course-1',
				'idempotency_key'       => 'training-plugin:course-1',
				'idempotency_mode'      => 'return_existing',
				'status'                => 'queued',
				'current_step'          => 'queued',
				'auto_release'          => 1,
				'certpsu_config_json'   => '{}',
				'class_payload_json'    => '{}',
				'template_payload_json' => '{}',
				'participant_count'     => 1,
			)
		);

		self::assertIsInt( $issuance_id );
		self::assertNotNull( $repo->find_latest_by_external_ref( 'training-plugin', 'course-1' ) );
	}
}
