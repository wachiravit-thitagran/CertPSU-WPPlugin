<?php
/**
 * Template Helper.
 *
 * @package CertPSU\Connector\Support
 */

declare(strict_types=1);

namespace CertPSU\Connector\Support;

/**
 * Handles loading and overriding templates.
 */
final class Template {

	/**
	 * Locate and load a template file.
	 *
	 * Checks the active theme first, then falls back to the plugin's default template.
	 *
	 * @param string               $template_name The name of the template file (e.g., 'my-certificates.php').
	 * @param array<string, mixed> $args          Variables to extract into the template scope.
	 * @return string The rendered template content.
	 */
	public static function load( string $template_name, array $args = array() ): string {
		// Look within active theme: `wp-content/themes/theme-name/certpsu/my-certificates.php`
		$theme_path = locate_template( 'certpsu/' . $template_name );

		if ( ! $theme_path ) {
			// Fallback to plugin default.
			$plugin_path = defined( 'CERTPSU_CONNECTOR_PATH' ) ? CERTPSU_CONNECTOR_PATH : '';
			$theme_path  = $plugin_path . 'templates/' . $template_name;
		}

		if ( ! file_exists( $theme_path ) ) {
			return '';
		}

		if ( ! empty( $args ) && is_array( $args ) ) {
			extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		}

		ob_start();
		include $theme_path;
		return ob_get_clean();
	}
}
