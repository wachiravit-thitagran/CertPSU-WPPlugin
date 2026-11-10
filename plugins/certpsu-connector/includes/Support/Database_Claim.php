<?php
/** Connection-owned MySQL claims shared by local workflow boundaries. */
declare(strict_types=1);
namespace CertPSU\Connector\Support;

final class Database_Claim {
	/** @var array<string,bool> */
	private static array $held = array();
	private static function name( string $scope ): string {
		global $wpdb;
		return 'certpsu:' . hash( 'sha1', ( defined( 'DB_NAME' ) ? DB_NAME : '' ) . ':' . $wpdb->prefix . ':' . $scope );
	}
	/** Never expire a claim while the owning connection may still mutate. */
	public static function acquire( string $scope, int $wait = 0 ): bool {
		global $wpdb;
		$name = self::name( $scope );
		if ( isset( self::$held[ $name ] ) || '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $name, $wait ) ) ) {
			return false;
		}
		self::$held[ $name ] = true;
		return true;
	}
	/** MySQL verifies connection ownership; disconnect releases automatically. */
	public static function release( string $scope ): void {
		global $wpdb;
		$name = self::name( $scope );
		if ( isset( self::$held[ $name ] ) ) {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
			unset( self::$held[ $name ] );
		}
	}
}
