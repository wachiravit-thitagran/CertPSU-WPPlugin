<?php
/**
 * Simple service container.
 *
 * @package CertPSU\Connector
 */

declare(strict_types=1);

namespace CertPSU\Connector;

/**
 * Container class.
 */
final class Container {

	/**
	 * Map of service instances.
	 *
	 * @var array<string,mixed>
	 */
	private array $services = array();

	/**
	 * Set a service.
	 *
	 * @param string $id Service ID.
	 * @param mixed  $service Service instance.
	 *
	 * @return void
	 */
	public function set( string $id, mixed $service ): void {
		$this->services[ $id ] = $service;
	}

	/**
	 * Get a service.
	 *
	 * @param string $id Service ID.
	 *
	 * @return mixed
	 */
	public function get( string $id ): mixed {
		return $this->services[ $id ] ?? null;
	}
}
