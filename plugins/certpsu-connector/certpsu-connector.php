<?php
/**
 * Plugin Name: CertPSU Connector
 * Plugin URI: https://github.com/wachiravit-thitagran/CertPSU-WPPlugin
 * Description: Connector plugin for async certificate issuance through cert.psu.ac.th.
 * Version: 0.1.5
 * Requires PHP: 8.2
 * Requires at least: 6.5
 *
 * @package CertPSU\Connector
 */

declare(strict_types=1);

use CertPSU\Connector\Bootstrap;
use CertPSU\Connector\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CERTPSU_CONNECTOR_VERSION', '0.1.5' );
define( 'CERTPSU_CONNECTOR_FILE', __FILE__ );
define( 'CERTPSU_CONNECTOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'CERTPSU_CONNECTOR_URL', plugin_dir_url( __FILE__ ) );
define( 'CERTPSU_CONNECTOR_DB_VERSION', '1' );

/**
 * Repository whose GitHub releases this plugin updates itself from. It must
 * match the `Plugin URI` header above; a typo in the owner or repository name
 * makes the GitHub API answer 404 exactly as it would for a repository with no
 * releases, so no update is ever offered and nothing is logged.
 *
 * Define it before the plugin loads, or use the `certpsu_github_repo` filter, to
 * track a fork instead.
 */
if ( ! defined( 'CERTPSU_CONNECTOR_GITHUB_REPO' ) ) {
	define( 'CERTPSU_CONNECTOR_GITHUB_REPO', 'wachiravit-thitagran/CertPSU-WPPlugin' );
}

// Custom Autoloader.
spl_autoload_register(
	function ( string $class_name ): void {
		$prefix = 'CertPSU\\Connector\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = str_replace( '\\', '/', $relative ) . '.php';
		$path     = CERTPSU_CONNECTOR_PATH . 'includes/' . $file;

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
);

// Optionally load a Composer autoloader for external deps. Prefer the
// plugin-local vendor (bundled in the distributed zip), then fall back to the
// monorepo-root vendor used during local development. Action Scheduler itself
// is loaded explicitly by Action_Scheduler_Loader, so this require is optional.
foreach (
	array(
		CERTPSU_CONNECTOR_PATH . 'vendor/autoload.php',
		dirname( __DIR__, 2 ) . '/vendor/autoload.php',
	) as $certpsu_autoload
) {
	if ( file_exists( $certpsu_autoload ) ) {
		require_once $certpsu_autoload;
		break;
	}
}
unset( $certpsu_autoload );

if ( function_exists( 'register_activation_hook' ) ) {
	register_activation_hook( CERTPSU_CONNECTOR_FILE, array( \CertPSU\Connector\Activation::class, 'activate' ) );
}

Bootstrap::init();

/**
 * Public helper function.
 *
 * @return Plugin
 */
if ( ! function_exists( 'certpsu' ) ) {
	function certpsu(): Plugin {
		return Bootstrap::plugin();
	}
}
