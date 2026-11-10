<?php
declare(strict_types=1);
namespace {
	if ( ! class_exists( 'WP_List_Table' ) ) {
		class WP_List_Table {}
	}
}
namespace CertPSU\Connector\Tests\Unit\Connector {
	final class IssuanceStatusTest extends \PHPUnit\Framework\TestCase {
		public function test_unknown_remote_outcome_is_visible_in_admin_status(): void {
			$table = new \CertPSU\Connector\Admin\Tables\Issuances_List_Table();
			$html = $table->column_default( array( 'status' => 'failed', 'failed_step' => 'create_class', 'last_error_code' => 'remote_outcome_unknown', 'last_error_message' => 'Administrator must reconcile with CertPSU before retrying.' ), 'status' );
			self::assertStringContainsString( 'remote_outcome_unknown', $html );
			self::assertStringContainsString( 'create_class', $html );
			self::assertStringContainsString( 'reconcile', $html );
		}
	}
}
