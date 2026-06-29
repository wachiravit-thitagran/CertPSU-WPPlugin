<?php
/**
 * Fetches selectable options (certificate templates, email templates, endorsers)
 * from the CertPSU API for use in settings dropdowns.
 *
 * Results are cached in transients and degrade gracefully: when the connector is
 * inactive or the API call fails, an empty array is returned so callers fall back
 * to manual id entry.
 *
 * @package CertPSU\TutorLMS
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS\Settings;

/**
 * Remote option provider for CertPSU resources.
 */
final class Remote_Options {

	/**
	 * Transient TTL in seconds.
	 */
	private const TTL = 300;

	/**
	 * Max rows to fetch (matches the API's MAX_PAGE_SIZE).
	 */
	private const MAX = 100;

	/**
	 * Resolve a schema field's `options_source` to an [id => label] map.
	 *
	 * @param string $source Source token (e.g. "certificate_template", "email_template:certificate", "endorser").
	 * @return array<string,string>
	 */
	public static function for_source( string $source ): array {
		if ( 'certificate_template' === $source ) {
			return self::certificate_templates();
		}
		if ( 'endorser' === $source ) {
			return self::endorsers();
		}
		if ( str_starts_with( $source, 'email_template:' ) ) {
			return self::email_templates( substr( $source, strlen( 'email_template:' ) ) );
		}
		return array();
	}

	/**
	 * Certificate templates as [id => name].
	 *
	 * @return array<string,string>
	 */
	public static function certificate_templates(): array {
		return self::cached(
			'certificate_templates',
			static function (): array {
				$response = certpsu()->api()->list_certificate_templates(
					array(
						'size'   => self::MAX,
						'status' => 'active',
					)
				);
				$out      = array();
				foreach ( self::items( $response ) as $item ) {
					$id = self::str( $item['id'] ?? '' );
					if ( '' === $id ) {
						continue;
					}
					$out[ $id ] = self::str( $item['name'] ?? '' ) ?: $id;
				}
				return $out;
			}
		);
	}

	/**
	 * Email templates of a given type as [id => name].
	 *
	 * @param string $type Email template type.
	 * @return array<string,string>
	 */
	public static function email_templates( string $type ): array {
		$all = self::email_templates_all();
		return $all[ $type ] ?? array();
	}

	/**
	 * All email templates grouped as [type => [id => name]].
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function email_templates_all(): array {
		return self::cached(
			'email_templates',
			static function (): array {
				$response = certpsu()->api()->list_email_templates( array( 'size' => self::MAX ) );
				$out      = array();
				foreach ( self::items( $response ) as $item ) {
					$id   = self::str( $item['id'] ?? '' );
					$type = self::str( $item['type'] ?? '' );
					if ( '' === $id || '' === $type ) {
						continue;
					}
					$out[ $type ][ $id ] = self::str( $item['name'] ?? '' ) ?: $id;
				}
				return $out;
			}
		);
	}

	/**
	 * Endorsers as [user_id => name]. The value is the CertPSU user id expected by
	 * the create-class endorsers payload.
	 *
	 * @return array<string,string>
	 */
	public static function endorsers(): array {
		return self::cached(
			'endorsers',
			static function (): array {
				$response = certpsu()->api()->list_endorsers(
					array(
						'size'   => self::MAX,
						'status' => 'active',
					)
				);
				$out      = array();
				foreach ( self::items( $response ) as $item ) {
					$user = is_array( $item['user'] ?? null ) ? $item['user'] : array();
					$uid  = self::str( $user['id'] ?? '' );
					if ( '' === $uid ) {
						continue;
					}
					$name = trim( self::str( $user['first_name'] ?? '' ) . ' ' . self::str( $user['last_name'] ?? '' ) );
					if ( '' === $name ) {
						$name = self::str( $user['username'] ?? '' ) ?: $uid;
					}
					$out[ $uid ] = $name;
				}
				return $out;
			}
		);
	}

	/**
	 * Run a producer behind a transient cache. Empty results are not cached so a
	 * transient API failure recovers on the next request.
	 *
	 * @param string   $key      Cache key suffix.
	 * @param callable $producer Returns the option map.
	 * @return array<string,mixed>
	 */
	private static function cached( string $key, callable $producer ): array {
		if ( ! function_exists( 'certpsu' ) ) {
			return array();
		}

		$cache_key = 'certpsu_tl_opts_' . $key;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		try {
			$result = $producer();
		} catch ( \Throwable $e ) {
			error_log( 'CertPSU Remote Options Error: ' . $e->getMessage() );
			$result = array();
		}

		if ( array() !== $result ) {
			set_transient( $cache_key, $result, self::TTL );
		}

		return $result;
	}

	/**
	 * Extract the list of item arrays from an API response.
	 *
	 * @param mixed $response Api_Response.
	 * @return array<int,array<string,mixed>>
	 */
	private static function items( mixed $response ): array {
		if ( ! is_object( $response ) || empty( $response->success ) || ! is_array( $response->data ) ) {
			return array();
		}
		return array_values( array_filter( $response->data, 'is_array' ) );
	}

	/**
	 * Coerce a value to a trimmed string.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function str( mixed $value ): string {
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}
}
