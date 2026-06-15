<?php
/**
 * Queue Jobs.
 *
 * @package CertPSU\Connector\Queue
 */

declare(strict_types=1);

namespace CertPSU\Connector\Queue;

/**
 * Registers jobs.
 */
final class Jobs {

	/**
	 * Constructor.
	 *
	 * @param \CertPSU\Connector\Container $container Container.
	 */
	public function __construct( private \CertPSU\Connector\Container $container ) {}

	/**
	 * Register actions.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		add_action(
			'certpsu_connector_process_issuance',
			function ( int $issuance_id ): void {
				$this->container->get( 'process_issuance_workflow_service' )->handle( $issuance_id );
			}
		);
		add_action(
			'certpsu_connector_release_issuance',
			function ( int $issuance_id ): void {
				$this->container->get( 'release_issuance_service' )->handle( $issuance_id );
			}
		);
		add_action(
			'certpsu_connector_retry_failed_step',
			function ( int $issuance_id ): void {
				$this->container->get( 'retry_failed_step_service' )->handle( $issuance_id );
			}
		);
		add_action(
			'certpsu_connector_refetch_certificate_urls',
			function ( int $issuance_id ): void {
				$this->container->get( 'refetch_certificate_urls_service' )->handle( $issuance_id );
			}
		);
		add_action(
			'certpsu_connector_poll_certificate_urls',
			function ( int $issuance_id ): void {
				$this->container->get( 'refetch_certificate_urls_service' )->handle( $issuance_id );
			}
		);
	}
}
