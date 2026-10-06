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
				'description' => 'Certificate issuance, status tracking, and related certificate workflow operations.',
			)
		);
	}

	public static function register_abilities(): void {
		wp_register_ability(
			'certpsu/get-issuance',
			array(
				'label'               => 'Get CertPSU Issuance',
				'description'         => 'Retrieves the current state and details of a certificate issuance by its issuance ID.',
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
				'description'         => 'Starts a certificate issuance workflow using the supplied issuance data and returns the resulting issuance record or error.',
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

	/**
	 * Get an issuance through the public facade.
	 *
	 * @param array<string,mixed> $input Ability input.
	 * @return mixed
	 */
	public static function get_issuance( array $input ): mixed {
		return certpsu()->get_issuance( (int) $input['issuance_id'] );
	}

	/**
	 * Create an issuance through the public facade.
	 *
	 * @param array<string,mixed> $input Ability input.
	 * @return mixed
	 */
	public static function create_issuance( array $input ): mixed {
		$result = certpsu()->create_issuance( $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return method_exists( $result, 'to_array' ) ? $result->to_array() : $result;
	}

	/**
	 * MCP metadata.
	 *
	 * @param bool $readonly Whether the ability is read-only.
	 * @return array<string,mixed>
	 */
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
