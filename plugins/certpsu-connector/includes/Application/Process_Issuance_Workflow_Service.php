<?php
/**
 * Process Issuance Workflow Service.
 *
 * @package CertPSU\Connector\Application
 */

declare(strict_types=1);

namespace CertPSU\Connector\Application;

use CertPSU\Connector\Support\Json;

/**
 * Main process workflow.
 */
final class Process_Issuance_Workflow_Service {

	/**
	 * Constructor.
	 *
	 * @param \CertPSU\Connector\Database\Repositories\Issuance_Repository    $issuances Issuances repo.
	 * @param \CertPSU\Connector\Database\Repositories\Certificate_Repository $certificates Certs repo.
	 * @param \CertPSU\Connector\CertPSU\CertPSU_Api_Client                   $client Client.
	 * @param \CertPSU\Connector\Queue\Queue                                  $queue Queue.
	 */
	public function __construct(
		private \CertPSU\Connector\Database\Repositories\Issuance_Repository $issuances,
		private \CertPSU\Connector\Database\Repositories\Certificate_Repository $certificates,
		private \CertPSU\Connector\CertPSU\CertPSU_Api_Client $client,
		private \CertPSU\Connector\Queue\Queue $queue
	) {}

	/**
	 * Handle.
	 *
	 * @param int $issuance_id ID.
	 * @return void
	 */
	public function handle( int $issuance_id ): void {
		$issuance = $this->issuances->find_by_id( $issuance_id );
		if ( ! $issuance ) {
			return;
		}

		$class_response = $this->client->create_class( $this->build_create_class_body( $issuance ) );
		if ( ! $class_response->success ) {
			$this->issuances->mark_failed( $issuance_id, 'create_class', $class_response->error_code, $class_response->error_message );
			do_action( 'certpsu_issuance_failed', $this->issuances->find_by_id( $issuance_id ) );
			return;
		}

		$class_id = (string) ( $class_response->data['id'] ?? '' );
		$this->issuances->mark_class_created( $issuance_id, $class_id );
		do_action( 'certpsu_issuance_class_created', $this->issuances->find_by_id( $issuance_id ) );

		$participants_response = $this->client->add_participants( $class_id, array( 'participants' => $this->certificates->build_participants_payload( $issuance_id ) ) );
		if ( ! $participants_response->success ) {
			$this->issuances->mark_failed( $issuance_id, 'add_participants', $participants_response->error_code, $participants_response->error_message );
			return;
		}

		$this->certificates->mark_all_for_issuance( $issuance_id, 'added_to_certpsu' );
		$this->issuances->mark_participants_added( $issuance_id );
		do_action( 'certpsu_issuance_participants_added', $this->issuances->find_by_id( $issuance_id ) );

		if ( 0 === (int) $issuance['auto_release'] ) {
			$this->issuances->mark_waiting_for_release( $issuance_id );
			do_action( 'certpsu_issuance_waiting_for_release', $this->issuances->find_by_id( $issuance_id ) );
			return;
		}

		$this->queue->enqueue_release_issuance( $issuance_id );
	}

	/**
	 * Build the API v2 `POST /v2/classes` request body.
	 *
	 * v2 receives everything through the body: the email templates that v1 sent
	 * as query parameters, the class definition, and the certificate template
	 * that v1 added via a separate call (`certificate_templates`). The
	 * organization is resolved from the API key server-side, so
	 * `organization_id` is intentionally not forwarded.
	 *
	 * @param array<string,mixed> $issuance Issuance row.
	 * @return array<string,mixed>
	 */
	private function build_create_class_body( array $issuance ): array {
		$config   = Json::decode( (string) $issuance['certpsu_config_json'] );
		$class    = Json::decode( (string) $issuance['class_payload_json'] );
		$template = Json::decode( (string) $issuance['template_payload_json'] );

		$body = array(
			'certificate_email_template'                   => $config['certificate_email_template'] ?? '',
			'endorser_required_endorsement_email_template' => $config['endorser_required_endorsement_email_template'] ?? '',
			'endorser_without_endorsement_email_template'  => $config['endorser_without_endorsement_email_template'] ?? '',
			'class_'                                       => $class,
		);

		if ( is_array( $template ) && array() !== $template ) {
			$body['certificate_templates'] = array( $template );
		}

		return $body;
	}
}
