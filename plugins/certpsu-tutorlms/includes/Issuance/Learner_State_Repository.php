<?php
/** Independent, site-local learner/course issuance records. */
declare(strict_types=1);
namespace CertPSU\TutorLMS\Issuance;

use CertPSU\Connector\Support\Database_Claim;
final class Learner_State_Repository {
	private function key( int $user_id, int $course_id ): string {
		return 'certpsu_learner_' . $user_id . '_' . $course_id;
	}
	/** @return array<string,mixed> */
	public function get( int $user_id, int $course_id ): array {
		global $wpdb;
		$key = $this->key( $user_id, $course_id );
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		$wpdb->last_error = '';
		$record           = get_option( $key, array() );
		if ( '' !== (string) $wpdb->last_error ) { // @phpstan-ignore-line -- WordPress may query storage.
			throw new \RuntimeException( 'Could not read the learner certificate checkpoint.' );
		}
		if ( is_array( $record ) && ! empty( $record ) ) {
			return $record;
		}
		$legacy    = get_user_meta( $user_id, '_certpsu_tutorlms_issued', true );
		$candidate = is_array( $legacy ) ? ( $legacy[ $course_id ] ?? array() ) : array();
		if ( ! is_array( $candidate ) || empty( $candidate['class_id'] ) || ! $this->owns_legacy( $course_id, $candidate ) ) {
			return array();
		}
		// Import only if absent. Never overwrite a newer independent record.
		add_option( $key, $candidate, '', false );
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		$wpdb->last_error = '';
		$record           = get_option( $key, array() );
		if ( '' !== (string) $wpdb->last_error ) { // @phpstan-ignore-line -- WordPress may query storage.
			throw new \RuntimeException( 'Could not read the learner certificate checkpoint.' );
		}
		if ( ! is_array( $record ) || empty( $record ) ) {
			throw new \RuntimeException( 'Could not migrate the legacy learner certificate checkpoint.' );
		}
		return $record;
	}
	/** Legacy usermeta has no site identity: import only proven ownership. */
	private function owns_legacy( int $course_id, array $record ): bool {
		$class_id = (string) $record['class_id'];
		if ( (string) get_post_meta( $course_id, Course_Class_Manager::CLASS_ID_META, true ) !== $class_id ) {
			return false;
		}
		if ( ! is_multisite() ) {
			return true;
		}
		$current = get_current_blog_id();
		if ( isset( $record['blog_id'] ) ) {
			return (int) $record['blog_id'] === $current;
		}
		$owners = array();
		foreach ( get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		) as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			try {
				if ( (string) get_post_meta( $course_id, Course_Class_Manager::CLASS_ID_META, true ) === $class_id ) {
					$owners[] = (int) $blog_id;
				}
			} finally {
				restore_current_blog();
			}
		}
		return array( $current ) === $owners;
	}
	/** Unique option_name is the atomic site/course/user uniqueness constraint. */
	public function save( int $user_id, int $course_id, array $data ): bool {
		$key = $this->key( $user_id, $course_id );
		if ( ! Database_Claim::acquire( 'learner:' . $key, 10 ) ) {
			return false;
		}
		try {
			$previous = $this->get( $user_id, $course_id );
			foreach ( array( 'class_id', 'participant_id' ) as $identity ) {
				if ( ! empty( $previous[ $identity ] ) && isset( $data[ $identity ] ) && $previous[ $identity ] !== $data[ $identity ] ) {
					return false;
				}
			}
			if ( ! empty( $previous['released'] ) ) {
				$data['released'] = true;
			}
			if ( ! empty( $previous['certificate_url'] ) && empty( $data['certificate_url'] ) ) {
				unset( $data['certificate_url'] );
			}
			$next = array_merge( $previous, $data );
			update_option( $key, $next, false );
			wp_cache_delete( $key, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			return get_option( $key ) === $next;
		} finally {
			Database_Claim::release( 'learner:' . $key );
		}
	}
	/** @return array<int,array<string,mixed>> */
	public function for_user( int $user_id ): array {
		global $wpdb;
		// Read legacy course IDs only to attempt a guarded, non-destructive migration.
		$legacy = get_user_meta( $user_id, '_certpsu_tutorlms_issued', true );
		if ( is_array( $legacy ) ) {
			foreach ( array_keys( $legacy ) as $course_id ) {
				$this->get( $user_id, (int) $course_id );
			}
		}
		$prefix  = 'certpsu_learner_' . $user_id . '_';
		$keys    = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix ) . '%' ) );
		$records = array();
		foreach ( $keys as $key ) {
			$course_id             = (int) substr( (string) $key, strlen( $prefix ) );
			$records[ $course_id ] = $this->get( $user_id, $course_id );
		}
		return $records;
	}
}
