<?php
/**
 * Settings Page.
 *
 * @package CertPSU\Connector\Admin
 */

declare(strict_types=1);

namespace CertPSU\Connector\Admin;

/**
 * Settings UI.
 */
final class Settings_Page {

	/**
	 * Render.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$settings   = certpsu()->container()->get( 'settings' )->all();
		$masked_key = '' !== $settings['api_key'] ? str_repeat( '•', 8 ) . substr( (string) $settings['api_key'], -4 ) : '';

		echo '<div class="wrap"><h1>CertPSU Connector Settings</h1>';
		echo '<form method="post">';
		wp_nonce_field( 'certpsu_connector_save_settings' );
		echo '<input type="password" name="api_key" value="" placeholder="' . esc_attr( $masked_key ) . '" />';
		echo '<label><input type="checkbox" name="delete_data_on_uninstall" value="1" ' . checked( (bool) $settings['delete_data_on_uninstall'], true, false ) . ' /> Delete data on uninstall</label>';
		echo '<input type="number" min="0" name="api_log_retention_days" value="' . esc_attr( (string) $settings['api_log_retention_days'] ) . '" />';
		submit_button( 'Save Settings' );
		echo '</form></div>';
	}
}
