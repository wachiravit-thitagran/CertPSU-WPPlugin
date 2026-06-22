<?php
/**
 * Test for Queue Bootstrap.
 *
 * @package CertPSU\Connector\Tests\Integration\Queue
 */

declare(strict_types=1);

namespace CertPSU\Connector\Tests\Integration\Queue;

use PHPUnit\Framework\TestCase;

/**
 * Tests Queue bootstrapping.
 */
final class QueueBootstrapTest extends TestCase {

	/**
	 * Ensure queue loads and resolves.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_queue_resolves(): void {
		require_once dirname( __DIR__, 3 ) . '/plugins/certpsu-connector/certpsu-connector.php';

		$queue = certpsu()->container()->get( 'queue' );
		self::assertInstanceOf( \CertPSU\Connector\Queue\Queue::class, $queue );
	}
}
