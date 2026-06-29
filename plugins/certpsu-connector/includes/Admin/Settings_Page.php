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

		$settings = $service->all();

		echo '<div class="wrap"><h1>CertPSU Connector Settings</h1>';
		echo '<form method="post">';
		wp_nonce_field( 'certpsu_connector_save_settings' );
		echo '<table class="form-table" role="presentation"><tbody>';

		// API key.
		echo '<tr>';
		echo '<th scope="row"><label for="certpsu_api_key">' . esc_html__( 'API key', 'certpsu-connector' ) . '</label></th>';
		echo '<td>';
		printf(
			'<input type="text" id="certpsu_api_key" name="api_key" value="%s" class="regular-text" placeholder="%s" />',
			esc_attr( (string) $settings['api_key'] ),
			esc_attr__( 'Enter your CertPSU API key', 'certpsu-connector' )
		);
		echo '<p class="description">' . esc_html__( 'Sent as the X-API-Key header to cert.psu.ac.th. The organization is derived from this key.', 'certpsu-connector' ) . '</p>';
		echo '</td></tr>';

		// API Base URL.
		echo '<tr>';
		echo '<th scope="row"><label for="certpsu_api_base_url">' . esc_html__( 'API Base URL', 'certpsu-connector' ) . '</label></th>';
		echo '<td>';
		printf(
			'<input type="url" id="certpsu_api_base_url" name="api_base_url" value="%s" class="regular-text" placeholder="https://cert.psu.ac.th:8443" />',
			esc_attr( (string) $settings['api_base_url'] )
		);
		echo '&nbsp;<button type="submit" name="test_connection" value="1" class="button button-secondary">' . esc_html__( 'Test Connection', 'certpsu-connector' ) . '</button>';
		echo '<p class="description">' . esc_html__( 'The endpoint URL to send requests to. Defaults to https://cert.psu.ac.th:8443', 'certpsu-connector' ) . '</p>';
		echo '</td></tr>';

		// Organization ID.
		echo '<tr>';
		echo '<th scope="row"><label for="certpsu_organization_id">' . esc_html__( 'Organization ID', 'certpsu-connector' ) . '</label></th>';
		echo '<td>';
		printf(
			'<input type="text" id="certpsu_organization_id" name="organization_id" value="%s" class="regular-text" placeholder="%s" />',
			esc_attr( (string) $settings['organization_id'] ),
			esc_attr__( 'Optional: Organization ID for dashboard links', 'certpsu-connector' )
		);
		echo '<p class="description">' . esc_html__( 'Currently used only to build the "View on CertPSU" links in the admin interface.', 'certpsu-connector' ) . '</p>';
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

		if ( isset( $_POST['api_key'] ) ) {
			$values['api_key'] = sanitize_text_field( trim( (string) wp_unslash( $_POST['api_key'] ) ) );
		}

		if ( isset( $_POST['api_base_url'] ) ) {
			$values['api_base_url'] = esc_url_raw( trim( (string) wp_unslash( $_POST['api_base_url'] ) ) );
		}

		if ( isset( $_POST['organization_id'] ) ) {
			$values['organization_id'] = sanitize_text_field( trim( (string) wp_unslash( $_POST['organization_id'] ) ) );
		}

		$service->update( $values );

		if ( ! empty( $_POST['test_connection'] ) ) {
			try {
				$api_client = certpsu()->api();
				$response   = $api_client->list_certificate_templates( array( 'size' => 1 ) );
				if ( $response->success ) {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Connection test successful! Valid API Key and Base URL.', 'certpsu-connector' ) . '</p></div>';
				} else {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sprintf( __( 'Connection test failed: %s', 'certpsu-connector' ), $response->error_message ) ) . '</p></div>';
				}
			} catch ( \Throwable $e ) {
				error_log( 'CertPSU Connection Test Error: ' . $e->getMessage() );
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sprintf( __( 'Connection test error: %s', 'certpsu-connector' ), $e->getMessage() ) ) . '</p></div>';
			}
		} else {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'certpsu-connector' ) . '</p></div>';
		}
	}
}
