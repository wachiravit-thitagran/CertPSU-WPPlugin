<?php
declare(strict_types=1);

namespace CertPSU\Connector\Tests\Unit\Connector;

use PHPUnit\Framework\TestCase;
use CertPSU\Connector\Container;

class ContainerTest extends TestCase {

	public function test_set_and_get(): void {
		$container = new Container();
		$service = new \stdClass();
		$service->name = 'Test Service';

		$container->set( 'my_service', $service );

		$this->assertSame( $service, $container->get( 'my_service' ) );
	}

	public function test_get_non_existent(): void {
		$container = new Container();

		$this->assertNull( $container->get( 'non_existent' ) );
	}
}
