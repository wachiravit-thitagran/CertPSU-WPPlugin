<?php
/**
 * Admin Menu.
 *
 * @package CertPSU\Connector\Admin
 */

declare(strict_types=1);

namespace CertPSU\Connector\Admin;

/**
 * Registers admin menus.
 */
final class Admin_Menu {

	/**
	 * Register menus.
	 *
	 * @return void
	 */
	public function register(): void {
		add_menu_page( 'CertPSU Connector', 'CertPSU Connector', 'manage_options', 'certpsu-connector-settings', array( clone $this, 'render_settings' ) );
		// Re-label the auto-created first submenu to "Settings". No callback here:
		// it shares the parent slug, so passing one would register the page render
		// action twice and output the page content twice.
		add_submenu_page( 'certpsu-connector-settings', 'Settings', 'Settings', 'manage_options', 'certpsu-connector-settings' );
		add_submenu_page( 'certpsu-connector-settings', 'Certificate Issuances', 'Certificate Issuances', 'manage_options', 'certpsu-connector-issuances', array( clone $this, 'render_issuances' ) );
		add_submenu_page( 'certpsu-connector-settings', 'API Logs', 'API Logs', 'manage_options', 'certpsu-connector-api-logs', array( clone $this, 'render_logs' ) );
	}

	/**
	 * Render settings.
	 *
	 * @return void
	 */
	public function render_settings(): void {
		certpsu()->container()->get( 'settings_page' )->render();
	}

	/**
	 * Render issuances.
	 *
	 * @return void
	 */
	public function render_issuances(): void {
		certpsu()->container()->get( 'issuances_list_page' )->render();
	}

	/**
	 * Render API Logs.
	 *
	 * @return void
	 */
	public function render_logs(): void {
		certpsu()->container()->get( 'api_logs_page' )->render();
	}
}
