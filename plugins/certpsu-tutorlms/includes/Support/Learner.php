<?php
/**
 * Resolves a CertPSU participant from a WordPress user.
 *
 * @package CertPSU\TutorLMS
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS\Support;

/**
 * Maps a WordPress user + course settings to a CertPSU participant payload.
 */
final class Learner {

	/**
	 * Build the participant payload for a learner.
	 *
	 * @param int                 $user_id  WordPress user ID.
	 * @param int                 $course_id Course ID (stored in `extra` for traceability).
	 * @param array<string,mixed> $settings Effective course settings.
	 * @return array<string,mixed>|null Participant payload, or null when the user has no email.
	 */
	public function participant( int $user_id, int $course_id, array $settings ): ?array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return null;
		}

		$email = sanitize_email( (string) $user->user_email );
		if ( '' === $email || ! is_email( $email ) ) {
			return null;
		}

		return array(
			'name'         => $this->resolve_name( $user, (string) ( $settings['name_source'] ?? 'display_name' ) ),
			'email'        => $email,
			'common_id'    => $this->resolve_common_id( $user_id, (string) ( $settings['common_id_meta_key'] ?? '' ) ),
			'organization' => null,
			'group'        => (string) ( $settings['template_group'] ?? 'participant' ),
			'extra'        => array(
				'tutorlms_course_id' => $course_id,
				'tutorlms_user_id'   => $user_id,
			),
		);
	}

	/**
	 * The reference used to release a single participant (id / common_id / email).
	 * Email is always present and unique, so it is the default reference.
	 *
	 * @param array<string,mixed> $participant Participant payload.
	 * @return string
	 */
	public function reference( array $participant ): string {
		return (string) ( $participant['email'] ?? '' );
	}

	/**
	 * Resolve the participant display name.
	 *
	 * @param \WP_User $user        User.
	 * @param string   $name_source 'display_name' or 'first_last'.
	 * @return string
	 */
	private function resolve_name( \WP_User $user, string $name_source ): string {
		if ( 'first_last' === $name_source ) {
			$name = trim( (string) $user->first_name . ' ' . (string) $user->last_name );
			if ( '' !== $name ) {
				return $name;
			}
		}
		return (string) $user->display_name;
	}

	/**
	 * Resolve the common ID (national ID) from a configurable user-meta key.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $meta_key Meta key (may be empty).
	 * @return string|null
	 */
	private function resolve_common_id( int $user_id, string $meta_key ): ?string {
		$meta_key = trim( $meta_key );
		if ( '' === $meta_key ) {
			return null;
		}
		$value = get_user_meta( $user_id, $meta_key, true );
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		return '' === $value ? null : $value;
	}
}
