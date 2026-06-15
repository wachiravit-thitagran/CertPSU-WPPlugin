<?php
/**
 * Public plugin facade.
 *
 * @package CertPSU\Connector
 */

declare(strict_types=1);

namespace CertPSU\Connector;

/**
 * Plugin class.
 */
final class Plugin {

	/**
	 * Constructor.
	 *
	 * @param Container $container Service container.
	 */
	public function __construct( private Container $container ) {
	}

	/**
	 * Get the service container.
	 *
	 * @return Container
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Create an issuance workflow.
	 *
	 * @param array<string,mixed> $args Args.
	 * @return \WP_Error|\CertPSU\Connector\DTO\Issuance_Result
	 */
	public function create_issuance( array $args ): \WP_Error|\CertPSU\Connector\DTO\Issuance_Result {
		return $this->container->get( 'create_issuance_service' )->handle( $args );
	}

	/**
	 * Low-level CertPSU API client.
	 *
	 * Use this for the "long-lived class" model (create a class once, then add
	 * participants and release certificates on-the-fly), as opposed to the
	 * batch `create_issuance()` workflow.
	 *
	 * @return \CertPSU\Connector\CertPSU\CertPSU_Api_Client
	 */
	public function api(): \CertPSU\Connector\CertPSU\CertPSU_Api_Client {
		return $this->container->get( 'certpsu_api_client' );
	}

	/**
	 * Get an issuance.
	 *
	 * @param int $issuance_id ID.
	 * @return \WP_Error|array<string,mixed>
	 */
	public function get_issuance( int $issuance_id ): \WP_Error|array {
		$issuance = $this->container->get( 'issuance_repository' )->find_by_id( $issuance_id );
		return $issuance ?? new \WP_Error( 'certpsu_issuance_not_found', 'Issuance not found.' );
	}
}
