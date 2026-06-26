<?php
/**
 * My Certificates Shortcode.
 *
 * @package CertPSU\Connector\Frontend
 */

declare(strict_types=1);

namespace CertPSU\Connector\Frontend;

use CertPSU\Connector\Support\Json;
use CertPSU\Connector\Support\Template;

/**
 * Handles the [certpsu_my_certificates] shortcode.
 */
final class My_Certificates_Shortcode {

	/**
	 * Certificate repository.
	 *
	 * @var \CertPSU\Connector\Database\Repositories\Certificate_Repository
	 */
	private \CertPSU\Connector\Database\Repositories\Certificate_Repository $certificate_repository;

	/**
	 * Constructor.
	 *
	 * @param \CertPSU\Connector\Database\Repositories\Certificate_Repository $certificate_repository Certificate repo.
	 */
	public function __construct( \CertPSU\Connector\Database\Repositories\Certificate_Repository $certificate_repository ) {
		$this->certificate_repository = $certificate_repository;
	}

	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'certpsu_my_certificates', array( $this, 'render' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @return string
	 */
	public function render(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your certificates.', 'certpsu-connector' ) . '</p>';
		}

		$user_id           = get_current_user_id();
		$core_certificates = $this->certificate_repository->get_by_wp_user_id( $user_id );

		// Format core certificates.
		$formatted_certs = array();
		foreach ( $core_certificates as $cert ) {
			$class_payload     = Json::decode( (string) $cert['class_payload_json'] );
			$formatted_certs[] = array(
				'id'              => $cert['certpsu_certificate_id'],
				'title'           => $class_payload['title'] ?? esc_html__( 'Unknown Certificate', 'certpsu-connector' ),
				'certificate_url' => $cert['certificate_url'],
				'issued_at'       => $cert['ready_at'] ?? $cert['updated_at'],
				'source'          => 'core',
			);
		}

		/**
		 * Filter the list of certificates displayed to the user.
		 *
		 * Plugins like TutorLMS can hook into this to inject their own certificates.
		 * Structure per item:
		 * [
		 *   'id'              => string,
		 *   'title'           => string,
		 *   'certificate_url' => string,
		 *   'issued_at'       => string (MySQL format Y-m-d H:i:s),
		 *   'source'          => string
		 * ]
		 *
		 * @param array<int,array<string,mixed>> $formatted_certs The formatted certificates.
		 * @param int                            $user_id         The WP User ID.
		 */
		$all_certs = apply_filters( 'certpsu_my_certificates_data', $formatted_certs, $user_id );

		// Sort by issued_at DESC.
		usort(
			$all_certs,
			function ( $a, $b ) {
				return strtotime( $b['issued_at'] ) <=> strtotime( $a['issued_at'] );
			}
		);

		return Template::load( 'my-certificates.php', array( 'certificates' => $all_certs ) );
	}
}
