<?php
/**
 * MCP / WordPress Abilities integration.
 *
 * @package CertPSU\Connector
 */

declare(strict_types=1);

namespace CertPSU\Connector;

defined( 'ABSPATH' ) || exit;

final class MCP {
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) || ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', array( self::class, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( self::class, 'register_abilities' ) );
	}

	public static function register_category(): void {
		wp_register_ability_category(
			'certpsu',
			array(
				'label'       => 'CertPSU',
				'description' => 'Certificate issuance operations provided by CertPSU Connector.',
			)
		);
	}

	public static function register_abilities(): void {
		wp_register_ability(
			'certpsu/get-issuance',
			array(
				'label'               => 'Get CertPSU Issuance',
				'description'         => 'Get one certificate issuance workflow by ID.',
				'category'            => 'certpsu',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'issuance_id' => array( 'type' => 'integer', 'minimum' => 1 ),
					),
					'required' => array( 'issuance_id' ),
				),
				'execute_callback'    => array( self::class, 'get_issuance' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'meta'                => self::meta( true ),
			)
		);

		wp_register_ability(
			'certpsu/create-issuance',
			array(
				'label'               => 'Create CertPSU Issuance',
				'description'         => 'Create an asynchronous CertPSU certificate issuance workflow.',
				'category'            => 'certpsu',
				'input_schema'        => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),
				'execute_callback'    => array( self::class, 'create_issuance' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'meta'                => self::meta( false ),
			)
		);
	}

	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public static function get_issuance( array $input ) {
		return certpsu()->get_issuance( (int) $input['issuance_id'] );
	}

	public static function create_issuance( array $input ) {
		$result = certpsu()->create_issuance( $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return method_exists( $result, 'to_array' ) ? $result->to_array() : $result;
	}

	private static function meta( bool $readonly ): array {
		return array(
			'mcp'         => array( 'public' => true, 'type' => 'tool' ),
			'annotations' => array(
				'readonly'      => $readonly,
				'destructive'   => false,
				'idempotent'    => $readonly,
				'openWorldHint' => ! $readonly,
			),
		);
	}
}
