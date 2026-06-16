<?php
/**
 * Global default settings page (seeds new courses).
 *
 * @package CertPSU\TutorLMS
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS\Admin;

use CertPSU\TutorLMS\Settings\Course_Settings;
use CertPSU\TutorLMS\Settings\Settings_Sanitizer;

/**
 * Settings → CertPSU Certificates page for global default values that apply to
 * courses without their own overrides.
 */
final class Defaults_Page {

	private const OPTION_GROUP = 'certpsu_tutorlms_defaults_group';
	private const MENU_SLUG    = 'certpsu-tutorlms-defaults';

	/**
	 * Constructor.
	 *
	 * @param Settings_Sanitizer $sanitizer Sanitizer.
	 */
	public function __construct( private Settings_Sanitizer $sanitizer = new Settings_Sanitizer() ) {}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	/**
	 * Add the options page.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_submenu_page(
			'certpsu-connector-settings',
			__( 'TutorLMS Integration', 'certpsu-tutorlms' ),
			__( 'TutorLMS Integration', 'certpsu-tutorlms' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Register the option + sanitize callback.
	 *
	 * @return void
	 */
	public function register_setting(): void {
		register_setting(
			self::OPTION_GROUP,
			Course_Settings::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize submitted defaults.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string,mixed>
	 */
	public function sanitize( mixed $input ): array {
		return $this->sanitizer->sanitize( is_array( $input ) ? $input : array() );
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$values   = Course_Settings::global_defaults();
		$renderer = new Field_Renderer( Course_Settings::OPTION_KEY );

		echo '<div class="wrap certpsu-tutorlms-settings">';
		echo '<h1>' . esc_html__( 'CertPSU Certificates — Defaults', 'certpsu-tutorlms' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'These values seed every course. Each course can override them on its editor screen.', 'certpsu-tutorlms' ) . '</p>';
		echo '<form method="post" action="options.php">';
		settings_fields( self::OPTION_GROUP );
		$renderer->render_all( $values );
		submit_button();
		echo '</form>';
		echo '</div>';
	}
}
