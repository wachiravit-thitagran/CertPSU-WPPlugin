<?php
/**
 * Workflow Job Test.
 *
 * @package CertPSU\Connector\Tests\Integration\Application
 */

declare(strict_types=1);

namespace CertPSU\Connector\Tests\Integration\Application;

use PHPUnit\Framework\TestCase;

/**
 * Tests workflow jobs.
 */
final class WorkflowJobTest extends TestCase {

	/**
	 * Setup.
	 */
	protected function setUp(): void {
		if ( ! function_exists( 'certpsu' ) ) {
			self::markTestSkipped( 'Integration tests require WordPress.' );
		}
	}

	/**
	 * Test workflow releases.
	 *
	 * @return void
	 */
	public function test_process_workflow_creates_class_adds_participants_and_releases(): void {
		$service = certpsu()->container()->get( 'process_issuance_workflow_service' );

		add_filter(
			'pre_http_request',
			static function ( $pre, array $args, string $url ) {
				$method = isset( $args['method'] ) ? strtoupper( (string) $args['method'] ) : 'GET';

				// API v2: create class (organization derives from the API key; the
				// certificate template is folded into the create body).
				if ( str_contains( $url, '/v2/classes' ) && ! str_contains( $url, '/participants' ) && ! str_contains( $url, '/release' ) ) {
					return array(
						'headers'  => array(),
						'body'     => wp_json_encode(
							array(
								'data' => array( 'id' => 'class-1' ),
								'meta' => array( 'request_id' => 'req_test' ),
							)
						),
						'response' => array(
							'code'    => 201,
							'message' => 'Created',
						),
					);
				}
				// API v2: add participants (POST) vs. poll participants (GET) share a path.
				if ( str_contains( $url, '/v2/classes/class-1/participants' ) ) {
					if ( 'POST' === $method ) {
						return array(
							'headers'  => array(),
							'body'     => wp_json_encode(
								array(
									'data' => array( 'participants' => array() ),
									'meta' => array( 'request_id' => 'req_test' ),
								)
							),
							'response' => array(
								'code'    => 201,
								'message' => 'Created',
							),
						);
					}

					return array(
						'headers'  => array(),
						'body'     => wp_json_encode(
							array(
								'data' => array(
									'participants' => array(
										'srv-abc' => array(
											'id'              => 'srv-abc',
											'email'           => 'alice@example.com',
											'certificate_id'  => 'cert-1',
											'certificate_url' => 'https://example.test/cert.pdf',
										),
									),
								),
								'meta' => array( 'request_id' => 'req_test' ),
							)
						),
						'response' => array(
							'code'    => 200,
							'message' => 'OK',
						),
					);
				}
				// API v2: release (async, HTTP 202).
				if ( str_contains( $url, '/v2/classes/class-1/release' ) ) {
					return array(
						'headers'  => array(),
						'body'     => wp_json_encode(
							array(
								'data' => array( 'participants' => array() ),
								'meta' => array( 'request_id' => 'req_test' ),
							)
						),
						'response' => array(
							'code'    => 202,
							'message' => 'Accepted',
						),
					);
				}
				return $pre;
			},
			10,
			3
		);

		$issuance = certpsu()->create_issuance(
			array(
				'external_source'      => 'training-plugin',
				'external_id'          => 'course-200',
				'certpsu'              => array(
					'organization_id'            => 'org-1',
					'certificate_email_template' => 'email-1',
					'endorser_required_endorsement_email_template' => 'email-2',
					'endorser_without_endorsement_email_template' => 'email-3',
				),
				'class'                => array(
					'name'                        => 'Class',
					'printed_name'                => 'Class',
					'started_date'                => '2026-06-01',
					'ended_date'                  => '2026-06-01',
					'issued_date'                 => '2026-06-01',
					'allow_duplicate_participant' => 'not_allowed',
					'auto_send_mail_participant'  => 'auto',
					'endorse_method'              => 'auto',
				),
				'certificate_template' => array(
					'name'     => 'Certificate',
					'group'    => 'participant',
					'template' => 'template-1',
				),
				'participants'         => array(
					array(
						'name'        => 'Alice',
						'email'       => 'alice@example.com',
						'external_id' => 'enrollment-1',
					),
				),
			)
		);

		$service->handle( $issuance->issuance_id );

		/**
		 * Stored issuance.
		 *
		 * @var array<string,mixed> $stored
		 */
		$stored = certpsu()->get_issuance( $issuance->issuance_id );
		self::assertSame( 'released', $stored['status'] );
	}
}
