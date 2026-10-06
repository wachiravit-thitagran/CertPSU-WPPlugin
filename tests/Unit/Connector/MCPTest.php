<?php
/**
 * MCP abilities tests.
 *
 * @package CertPSU\Connector\Tests
 */

declare(strict_types=1);

use CertPSU\Connector\MCP;
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wp_register_ability_category' ) ) {
	function wp_register_ability_category( $name, $args ) {
		$GLOBALS['mock_ability_categories'][ $name ] = $args;
	}
}

if ( ! function_exists( 'wp_register_ability' ) ) {
	function wp_register_ability( $name, $args ) {
		$GLOBALS['mock_abilities'][ $name ] = $args;
	}
}

final class MCPTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['mock_ability_categories'] = array();
		$GLOBALS['mock_abilities']          = array();
	}

	public function test_registers_certpsu_category_and_abilities(): void {
		MCP::register_category();
		MCP::register_abilities();

		$this->assertArrayHasKey( 'certpsu', $GLOBALS['mock_ability_categories'] );
		$this->assertArrayHasKey( 'certpsu/get-issuance', $GLOBALS['mock_abilities'] );
		$this->assertArrayHasKey( 'certpsu/create-issuance', $GLOBALS['mock_abilities'] );
		$this->assertTrue( $GLOBALS['mock_abilities']['certpsu/get-issuance']['meta']['mcp']['public'] );
	}

	public function test_get_issuance_executes_public_plugin_api(): void {
		$result = MCP::get_issuance( array( 'issuance_id' => 7 ) );

		$this->assertSame( 7, $result['id'] );
		$this->assertSame( 'released', $result['status'] );
	}

	public function test_manage_permission_is_required(): void {
		$GLOBALS['mock_current_user_id'] = 2;
		$this->assertFalse( MCP::can_manage() );

		$GLOBALS['mock_current_user_id'] = 1;
		$this->assertTrue( MCP::can_manage() );
	}
}
