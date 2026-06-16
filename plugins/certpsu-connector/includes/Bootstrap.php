<?php
/**
 * Plugin bootstrap orchestration.
 *
 * @package CertPSU\Connector
 */

declare(strict_types=1);

namespace CertPSU\Connector;

/**
 * Bootstrap class.
 */
final class Bootstrap {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $plugin = null;

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( self::$plugin instanceof Plugin ) {
			return;
		}

		( new \CertPSU\Connector\Queue\Action_Scheduler_Loader() )->load();

		$container = new Container();
		$container->set( 'template_replacer', new \CertPSU\Connector\Utils\Template_Replacer() );
		$container->set( 'settings', new \CertPSU\Connector\Support\Settings() );
		$container->set( 'queue', new \CertPSU\Connector\Queue\Queue() );
		$container->set( 'issuance_repository', new \CertPSU\Connector\Database\Repositories\Issuance_Repository() );
		$container->set( 'certificate_repository', new \CertPSU\Connector\Database\Repositories\Certificate_Repository() );
		$container->set( 'api_log_repository', new \CertPSU\Connector\Database\Repositories\Api_Log_Repository() );
		$container->set( 'validator', new \CertPSU\Connector\Support\Validator() );
		$container->set(
			'create_issuance_service',
			new \CertPSU\Connector\Application\Create_Issuance_Service(
				$container->get( 'validator' ),
				$container->get( 'issuance_repository' ),
				$container->get( 'certificate_repository' ),
				$container->get( 'queue' ),
				$container->get( 'settings' )
			)
		);

		$container->set( 'payload_sanitizer', new \CertPSU\Connector\Support\Payload_Sanitizer() );
		$container->set( 'participant_response_normalizer', new \CertPSU\Connector\CertPSU\Participant_Response_Normalizer() );
		$container->set(
			'certpsu_api_client',
			new \CertPSU\Connector\CertPSU\CertPSU_Api_Client(
				$container->get( 'settings' ),
				$container->get( 'api_log_repository' ),
				$container->get( 'payload_sanitizer' )
			)
		);

		$container->set(
			'process_issuance_workflow_service',
			new \CertPSU\Connector\Application\Process_Issuance_Workflow_Service(
				$container->get( 'issuance_repository' ),
				$container->get( 'certificate_repository' ),
				$container->get( 'certpsu_api_client' ),
				$container->get( 'queue' )
			)
		);
		$container->set(
			'release_issuance_service',
			new \CertPSU\Connector\Application\Release_Issuance_Service(
				$container->get( 'issuance_repository' ),
				$container->get( 'certificate_repository' ),
				$container->get( 'certpsu_api_client' ),
				$container->get( 'queue' )
			)
		);
		$container->set(
			'retry_failed_step_service',
			new \CertPSU\Connector\Application\Retry_Failed_Step_Service(
				$container->get( 'issuance_repository' ),
				$container->get( 'queue' )
			)
		);
		$container->set(
			'refetch_certificate_urls_service',
			new \CertPSU\Connector\Application\Refetch_Certificate_Urls_Service(
				$container->get( 'issuance_repository' ),
				$container->get( 'certificate_repository' ),
				$container->get( 'certpsu_api_client' ),
				$container->get( 'participant_response_normalizer' ),
				$container->get( 'queue' )
			)
		);

		( new \CertPSU\Connector\Queue\Jobs( $container ) )->register();

		$container->set( 'admin_menu', new \CertPSU\Connector\Admin\Admin_Menu() );
		$container->set( 'settings_page', new \CertPSU\Connector\Admin\Settings_Page() );
		$container->set( 'issuances_list_page', new \CertPSU\Connector\Admin\Issuances_List_Page() );
		$container->set( 'api_logs_page', new \CertPSU\Connector\Admin\Api_Logs_Page() );

		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_menu', array( $container->get( 'admin_menu' ), 'register' ) );
			add_action(
				'admin_enqueue_scripts',
				static function ( string $hook ): void {
					if ( ! str_contains( $hook, 'certpsu-connector' ) ) {
						return;
					}
					$url = defined( 'CERTPSU_CONNECTOR_URL' ) ? CERTPSU_CONNECTOR_URL : '';
					wp_enqueue_style( 'certpsu-connector-admin', $url . 'assets/admin.css', array(), CERTPSU_CONNECTOR_VERSION );
					wp_enqueue_script( 'certpsu-connector-admin', $url . 'assets/admin.js', array( 'jquery' ), CERTPSU_CONNECTOR_VERSION, true );
				}
			);
		}

		self::$plugin = new Plugin( $container );
	}

	/**
	 * Get the plugin instance.
	 *
	 * @return Plugin
	 */
	public static function plugin(): Plugin {
		if ( ! self::$plugin instanceof Plugin ) {
			self::init();
		}

		return self::$plugin;
	}
}
