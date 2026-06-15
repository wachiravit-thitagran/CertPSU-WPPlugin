<?php
/**
 * Issuances List Page.
 *
 * @package CertPSU\Connector\Admin
 */

declare(strict_types=1);

namespace CertPSU\Connector\Admin;

/**
 * Issuances page.
 */
final class Issuances_List_Page {

	/**
	 * Handle actions.
	 *
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$action      = sanitize_key( $_GET['action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$issuance_id = isset( $_GET['issuance_id'] ) ? (int) $_GET['issuance_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'release' === $action ) {
			check_admin_referer( 'certpsu_release_' . $issuance_id );
			certpsu()->container()->get( 'queue' )->enqueue_release_issuance( $issuance_id );
		}

		if ( 'retry_failed_step' === $action ) {
			check_admin_referer( 'certpsu_retry_' . $issuance_id );
			certpsu()->container()->get( 'queue' )->enqueue_retry_failed_step( $issuance_id );
		}

		if ( 'refetch_urls' === $action ) {
			check_admin_referer( 'certpsu_refetch_' . $issuance_id );
			certpsu()->container()->get( 'queue' )->enqueue_refetch_certificate_urls( $issuance_id );
		}
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		if ( ! class_exists( '\WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		$table = new \CertPSU\Connector\Admin\Tables\Issuances_List_Table();
		$table->prepare_items();

		echo '<div class="wrap"><h1>Issuances</h1>';
		$table->display();
		echo '</div>';
	}
}
