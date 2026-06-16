<?php
/**
 * My Certificates Integration.
 *
 * @package CertPSU\TutorLMS\Integration
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS\Integration;

/**
 * Injects TutorLMS certificates into the core "My Certificates" table.
 */
final class My_Certificates_Integration {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'certpsu_my_certificates_data', array( $this, 'inject_tutorlms_certificates' ), 10, 2 );
	}

	/**
	 * Inject TutorLMS certificates.
	 *
	 * @param array<int,array<string,mixed>> $certs   Existing certificates.
	 * @param int                            $user_id WP User ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function inject_tutorlms_certificates( array $certs, int $user_id ): array {
		$issued_state = get_user_meta( $user_id, '_certpsu_tutorlms_issued', true );
		if ( ! is_array( $issued_state ) || empty( $issued_state ) ) {
			return $certs;
		}

		foreach ( $issued_state as $course_id => $data ) {
			if ( empty( $data['released'] ) ) {
				continue;
			}

			// Format matches the core shortcode array structure.
			$certs[] = array(
				'id'              => $data['certificate_id'] ?? $data['participant_id'],
				'title'           => get_the_title( $course_id ) ?: esc_html__( 'Unknown Course', 'certpsu-tutorlms' ),
				'certificate_url' => $data['certificate_url'] ?? '',
				'issued_at'       => $data['at'] ?? gmdate( 'Y-m-d H:i:s' ),
				'source'          => 'tutorlms',
			);
		}

		return $certs;
	}
}
