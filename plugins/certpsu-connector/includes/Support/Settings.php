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
				'api_key'                  => '',
				'delete_data_on_uninstall' => false,
				'api_log_retention_days'   => 0,
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
}
