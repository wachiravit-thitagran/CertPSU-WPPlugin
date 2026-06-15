<?php
/**
 * Admin Actions Test.
 *
 * @package CertPSU\Connector\Tests\Integration\Admin
 */

declare(strict_types=1);

namespace CertPSU\Connector\Tests\Integration\Admin;

use PHPUnit\Framework\TestCase;

/**
 * Tests admin actions.
 */
final class AdminActionsTest extends TestCase {

	/**
	 * Setup.
	 */
	protected function setUp(): void {
		if ( ! function_exists( 'certpsu' ) ) {
			self::markTestSkipped( 'Integration tests require WordPress.' );
		}
	}

	/**
	 * Test release action requires manage_options and nonce.
	 *
	 * @return void
	 */
	public function test_release_action_requires_manage_options_and_nonce(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) ); // @phpstan-ignore-line

		$_GET['page']        = 'certpsu-connector-issuances';
		$_GET['action']      = 'release';
		$_GET['issuance_id'] = '1';

		$page = new \CertPSU\Connector\Admin\Issuances_List_Page();

		$this->expectException( \WPDieException::class ); // @phpstan-ignore-line
		$page->handle_actions();
	}
}
