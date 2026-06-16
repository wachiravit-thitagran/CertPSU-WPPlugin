<?php
/**
 * Settings support.
 *
 * @package CertPSU\Connector\Support
 */

declare(strict_types=1);

namespace CertPSU\Connector\Support;

/**
 * Settings class.
 */
final class Settings {

	/**
	 * Option key.
	 */
	private const OPTION_KEY = 'certpsu_connector_settings';

	/**
	 * Get all settings.
	 *
	 * @return array<string,mixed>
	 */
	public function all(): array {
		return wp_parse_args(
			get_option( self::OPTION_KEY, array() ),
			array(
				'api_key'                => '',
				'organization_id'        => '',
				'api_log_retention_days' => 0,
			)
		);
	}

	/**
	 * Get API key.
	 *
	 * @return string
	 */
	public function api_key(): string {
		$all = $this->all();
		return isset( $all['api_key'] ) ? (string) $all['api_key'] : '';
	}

	/**
	 * Get Organization ID.
	 *
	 * @return string
	 */
	public function organization_id(): string {
		$all = $this->all();
		return isset( $all['organization_id'] ) ? (string) $all['organization_id'] : '';
	}

	/**
	 * Persist a partial set of settings, merged over the current values.
	 *
	 * @param array<string,mixed> $values Values to update.
	 * @return void
	 */
	public function update( array $values ): void {
		update_option( self::OPTION_KEY, array_merge( $this->all(), $values ) );
	}
}
