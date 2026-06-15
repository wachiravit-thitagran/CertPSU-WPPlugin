<?php
/**
 * Validator.
 *
 * @package CertPSU\Connector\Support
 */

declare(strict_types=1);

namespace CertPSU\Connector\Support;

use WP_Error;

/**
 * Validates issuance.
 */
final class Validator {

	/**
	 * Validate create issuance args.
	 *
	 * @param array<string,mixed> $args Args.
	 * @return array<string,mixed>|WP_Error
	 */
	public function validate_create_issuance( array $args ): array|WP_Error {
		$args['idempotency_mode']                     = $args['idempotency_mode'] ?? 'return_existing';
		$args['auto_release']                         = $args['auto_release'] ?? true;
		$args['class']['description']                 = $args['class']['description'] ?? null;
		$args['class']['class_date_text']             = $args['class']['class_date_text'] ?? null;
		$args['class']['instructors']                 = $args['class']['instructors'] ?? array();
		$args['class']['tags']                        = $args['class']['tags'] ?? array();
		$args['class']['allow_duplicate_participant'] = $args['class']['allow_duplicate_participant'] ?? 'not_allowed';
		$args['class']['auto_send_mail_participant']  = $args['class']['auto_send_mail_participant'] ?? 'auto';
		$args['class']['endorse_method']              = $args['class']['endorse_method'] ?? 'auto';
		$args['certificate_template']['group']        = $args['certificate_template']['group'] ?? 'participant';
		$args['certificate_template']['remark']       = $args['certificate_template']['remark'] ?? null;

		$participants = isset( $args['participants'] ) && is_array( $args['participants'] ) ? $args['participants'] : array();

		foreach ( $participants as $index => $participant ) {
			if ( ! is_array( $participant ) ) {
				continue;
			}
			$participants[ $index ]['organization'] = $participant['organization'] ?? null;
			$participants[ $index ]['common_id']    = $participant['common_id'] ?? null;
			$participants[ $index ]['group']        = $participant['group'] ?? $args['certificate_template']['group'];
			$participants[ $index ]['extra']        = $participant['extra'] ?? array();
		}
		$args['participants'] = $participants;

		$duplicates = $this->duplicate_emails( $args['participants'] );
		if ( ! empty( $duplicates ) ) {
			return new WP_Error( 'certpsu_duplicate_participants', 'Duplicate participant email(s): ' . implode( ', ', $duplicates ) );
		}

		return $args;
	}

	/**
	 * Find duplicates.
	 *
	 * @param array<int,array<string,mixed>> $participants Participants.
	 * @return array<int,string>
	 */
	private function duplicate_emails( array $participants ): array {
		$emails = array_map(
			static function ( array $participant ): string {
				return strtolower( (string) ( $participant['email'] ?? '' ) );
			},
			$participants
		);
		return array_values( array_unique( array_filter( array_diff_assoc( $emails, array_unique( $emails ) ) ) ) );
	}
}
