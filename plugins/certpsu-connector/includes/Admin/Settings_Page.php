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

		$service = certpsu()->container()->get( 'settings' );
		$this->maybe_save( $service );

		$settings   = $service->all();
		$masked_key = '' !== $settings['api_key'] ? str_repeat( '•', 8 ) . substr( (string) $settings['api_key'], -4 ) : '';

		echo '<div class="wrap"><h1>CertPSU Connector Settings</h1>';
		echo '<form method="post">';
		wp_nonce_field( 'certpsu_connector_save_settings' );
		echo '<table class="form-table" role="presentation"><tbody>';

		// API key.
		echo '<tr>';
		echo '<th scope="row"><label for="certpsu_api_key">' . esc_html__( 'API key', 'certpsu-connector' ) . '</label></th>';
		echo '<td>';
		printf(
			'<input type="password" id="certpsu_api_key" name="api_key" value="" class="regular-text" autocomplete="new-password" placeholder="%s" />',
			esc_attr( '' !== $masked_key ? $masked_key : __( 'Enter your CertPSU API key', 'certpsu-connector' ) )
		);
		echo '<p class="description">' . esc_html__( 'Sent as the X-API-Key header to cert.psu.ac.th. The organization is derived from this key. Leave blank to keep the current key.', 'certpsu-connector' ) . '</p>';
		echo '</td></tr>';

		// API log retention.
		echo '<tr>';
		echo '<th scope="row"><label for="certpsu_api_log_retention_days">' . esc_html__( 'API log retention (days)', 'certpsu-connector' ) . '</label></th>';
		echo '<td>';
		printf(
			'<input type="number" min="0" step="1" id="certpsu_api_log_retention_days" name="api_log_retention_days" value="%s" class="small-text" />',
			esc_attr( (string) $settings['api_log_retention_days'] )
		);
		echo '<p class="description">' . esc_html__( 'How many days to keep sanitized API request/response logs. Use 0 to keep them forever.', 'certpsu-connector' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';
		submit_button( __( 'Save Settings', 'certpsu-connector' ) );
		echo '</form></div>';
	}

	/**
	 * Handle the settings form submission.
	 *
	 * @param \CertPSU\Connector\Support\Settings $service Settings service.
	 * @return void
	 */
	private function maybe_save( \CertPSU\Connector\Support\Settings $service ): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ), 'certpsu_connector_save_settings' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$values = array(
			'api_log_retention_days' => isset( $_POST['api_log_retention_days'] ) ? max( 0, (int) $_POST['api_log_retention_days'] ) : 0,
		);

		// Only overwrite the API key when a new value is entered (the field is
		// rendered empty and shows a masked placeholder).
		$api_key = isset( $_POST['api_key'] ) ? trim( (string) wp_unslash( $_POST['api_key'] ) ) : '';
		if ( '' !== $api_key ) {
			$values['api_key'] = sanitize_text_field( $api_key );
		}

		$service->update( $values );

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'certpsu-connector' ) . '</p></div>';
	}
}
