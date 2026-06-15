<?php
/**
 * Sanitizes submitted settings against the schema.
 *
 * @package CertPSU\TutorLMS
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS\Settings;

/**
 * Converts a raw input array (from a course metabox or the defaults page) into a
 * clean settings array, validated against {@see Course_Settings::schema()}.
 */
final class Settings_Sanitizer {

	/**
	 * Sanitize raw input.
	 *
	 * @param array<string,mixed> $raw Raw input (already unslashed).
	 * @return array<string,mixed>
	 */
	public function sanitize( array $raw ): array {
		$clean = array();

		foreach ( Course_Settings::schema() as $section ) {
			foreach ( $section['fields'] as $key => $field ) {
				$type  = $field['type'] ?? 'text';
				$value = $raw[ $key ] ?? null;

				switch ( $type ) {
					case 'checkbox':
						$clean[ $key ] = ! empty( $value );
						break;

					case 'select':
						$options       = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
						$value         = is_string( $value ) ? $value : '';
						$clean[ $key ] = array_key_exists( $value, $options )
							? $value
							: ( $field['default'] ?? '' );
						break;

					case 'textarea':
						$clean[ $key ] = sanitize_textarea_field( (string) ( is_string( $value ) ? $value : '' ) );
						break;

					case 'date':
						$clean[ $key ] = $this->sanitize_date( is_string( $value ) ? $value : '' );
						break;

					case 'list':
						$clean[ $key ] = $this->sanitize_list( $value );
						break;

					case 'endorsers':
						$clean[ $key ] = $this->sanitize_endorsers( $value );
						break;

					case 'text':
					default:
						$clean[ $key ] = sanitize_text_field( (string) ( is_string( $value ) ? $value : '' ) );
						break;
				}
			}
		}

		return $clean;
	}

	/**
	 * Accept only YYYY-MM-DD, else empty.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function sanitize_date( string $value ): string {
		$value = trim( $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}

	/**
	 * Sanitize a newline- or array-based list into a clean string array.
	 *
	 * @param mixed $value Value.
	 * @return array<int,string>
	 */
	private function sanitize_list( mixed $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/\r\n|\r|\n/', $value ) ?: array();
		}
		if ( ! is_array( $value ) ) {
			return array();
		}
		$items = array();
		foreach ( $value as $item ) {
			$item = sanitize_text_field( (string) $item );
			if ( '' !== $item ) {
				$items[] = $item;
			}
		}
		return array_values( $items );
	}

	/**
	 * Sanitize the endorsers repeater.
	 *
	 * @param mixed $value Value.
	 * @return array<int,array<string,string>>
	 */
	private function sanitize_endorsers( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$allowed_requirement = array( 'required', 'not_required' );
		$allowed_auto_send   = array( 'auto', 'not_auto' );

		$endorsers = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$endorser_id = sanitize_text_field( (string) ( $row['endorser_id'] ?? '' ) );
			$user        = sanitize_text_field( (string) ( $row['user'] ?? '' ) );
			$name        = sanitize_text_field( (string) ( $row['name'] ?? '' ) );

			// A usable endorser needs at least these three.
			if ( '' === $endorser_id || '' === $user || '' === $name ) {
				continue;
			}

			$requirement = (string) ( $row['endorse_requirement'] ?? 'required' );
			$auto_send   = (string) ( $row['auto_send_mail_to_endorse'] ?? 'auto' );

			$endorsers[] = array(
				'endorser_id'               => $endorser_id,
				'user'                      => $user,
				'name'                      => $name,
				'position'                  => sanitize_text_field( (string) ( $row['position'] ?? '' ) ),
				'endorse_requirement'       => in_array( $requirement, $allowed_requirement, true ) ? $requirement : 'required',
				'auto_send_mail_to_endorse' => in_array( $auto_send, $allowed_auto_send, true ) ? $auto_send : 'auto',
			);
		}

		return $endorsers;
	}
}
