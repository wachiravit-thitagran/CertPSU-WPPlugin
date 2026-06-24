<?php
/**
 * Container Keys Test.
 *
 * @package CertPSU\Connector\Tests\Integration\Connector
 */

declare(strict_types=1);

namespace CertPSU\Connector\Tests\Integration\Connector;

use PHPUnit\Framework\TestCase;
use CertPSU\Connector\Bootstrap;

/**
 * Ensures that commonly requested services resolve correctly from the container
 * to prevent null pointer exceptions like the "certificates" vs "certificate_repository" issue.
 */
final class ContainerKeysTest extends TestCase {

	/**
	 * Test all core services exist in the container.
	 *
	 * @return void
	 */
	public function test_all_expected_keys_are_resolvable(): void {
		Bootstrap::init();

		$container = certpsu()->container();

		$expected_keys = array(
			'template_replacer',
			'settings',
			'queue',
			'issuance_repository',
			'certificate_repository',
			'api_log_repository',
			'validator',
			'create_issuance_service',
			'payload_sanitizer',
			'participant_response_normalizer',
			'certpsu_api_client',
			'process_issuance_workflow_service',
			'release_issuance_service',
			'retry_failed_step_service',
			'refetch_certificate_urls_service',
			'admin_menu',
			'settings_page',
			'issuances_list_page',
			'api_logs_page',
		);

		foreach ( $expected_keys as $key ) {
			self::assertNotNull(
				$container->get( $key ),
				"Container failed to resolve key '{$key}'. This might lead to fatal errors."
			);
		}
	}
}
