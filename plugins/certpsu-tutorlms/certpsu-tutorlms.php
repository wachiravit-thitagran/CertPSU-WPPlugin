<?php
/**
 * Plugin Name: CertPSU TutorLMS Bridge
 * Plugin URI: https://github.com/wachiravit-thitagran/CertPSU-WPPlugin
 * Description: Intercepts TutorLMS course completion and queues certificate issuance via CertPSU.
 * Version: 0.1.5
 * Requires PHP: 8.2
 * Requires at least: 6.5
 *
 * @package CertPSU\TutorLMS
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CERTPSU_TUTORLMS_VERSION', '0.1.5' );
define( 'CERTPSU_TUTORLMS_FILE', __FILE__ );
define( 'CERTPSU_TUTORLMS_PATH', plugin_dir_path( __FILE__ ) );
define( 'CERTPSU_TUTORLMS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Repository whose GitHub releases this plugin updates itself from. It must
 * match the `Plugin URI` header above; a typo in the owner or repository name
 * makes the GitHub API answer 404 exactly as it would for a repository with no
 * releases, so no update is ever offered and nothing is logged.
 *
 * Define it before the plugin loads, or use the `certpsu_github_repo` filter, to
 * track a fork instead.
 */
if ( ! defined( 'CERTPSU_TUTORLMS_GITHUB_REPO' ) ) {
	define( 'CERTPSU_TUTORLMS_GITHUB_REPO', 'wachiravit-thitagran/CertPSU-WPPlugin' );
}

spl_autoload_register(
	function ( string $class_name ): void {
		$prefix = 'CertPSU\\TutorLMS\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = str_replace( '\\', '/', $relative ) . '.php';
		$path     = CERTPSU_TUTORLMS_PATH . 'includes/' . $file;

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
);

/**
 * Boot the TutorLMS bridge plugin.
 *
 * @return void
 */
function boot_certpsu_tutorlms(): void {
	static $plugin = null;
	if ( null === $plugin ) {
		$plugin = new Plugin();
		$plugin->init();
	}
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\boot_certpsu_tutorlms' );
}
