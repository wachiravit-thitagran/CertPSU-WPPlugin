<?php
/**
 * Page Installer.
 *
 * @package CertPSU\Connector\Database
 */

declare(strict_types=1);

namespace CertPSU\Connector\Database;

/**
 * Handles automatic creation of plugin pages on activation.
 */
final class Page_Installer {

	/**
	 * Run the installer.
	 *
	 * @return void
	 */
	public function install(): void {
		$this->create_my_certificates_page();
	}

	/**
	 * Create the "My Certificates" page if it doesn't exist.
	 *
	 * @return void
	 */
	private function create_my_certificates_page(): void {
		$page_slug = 'my-certificate';

		// Check if page already exists by slug.
		$existing_page = get_page_by_path( $page_slug );
		if ( $existing_page ) {
			return;
		}

		// Check if shortcode is used anywhere.
		global $wpdb;
		$shortcode_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status IN ('publish', 'draft') AND post_content LIKE '%[certpsu_my_certificates]%' LIMIT 1"
		);

		if ( $shortcode_exists ) {
			return;
		}

		// Create the page.
		$page_data = array(
			'post_title'   => 'เกียรติบัตรของฉัน', // Default title in Thai based on user request.
			'post_name'    => $page_slug,
			'post_content' => "<!-- wp:shortcode -->\n[certpsu_my_certificates]\n<!-- /wp:shortcode -->",
			'post_status'  => 'publish',
			'post_type'    => 'page',
		);

		wp_insert_post( $page_data );
	}
}
