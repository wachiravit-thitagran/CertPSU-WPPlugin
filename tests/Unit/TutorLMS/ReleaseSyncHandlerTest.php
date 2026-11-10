<?php
declare(strict_types=1);

namespace {
	if ( ! class_exists( 'WP_Query' ) ) {
		class WP_Query {
			public array $posts;
			public function __construct( array $args ) {
				$GLOBALS['release_sync_query'] = $args;
				$this->posts = 'courses' === $args['post_type'] ? array( 123 ) : array();
			}
		}
	}
}

namespace CertPSU\Connector\Tests\Unit\TutorLMS {
	final class ReleaseSyncHandlerTest extends \PHPUnit\Framework\TestCase {
		public function test_periodic_sync_queues_real_tutor_course(): void {
			$GLOBALS['mock_post_meta'][123]['_certpsu_class_id'] = 'class-existing';
			$GLOBALS['mock_post_meta'][123][\CertPSU\TutorLMS\Issuance\Course_Class_Manager::CLASS_ID_META] = 'class-existing';
			$GLOBALS['mock_async_actions'] = array();
			( new \CertPSU\TutorLMS\Issuance\Release_Sync_Handler() )->sync_all_classes();
			self::assertSame( 'courses', $GLOBALS['release_sync_query']['post_type'] );
			self::assertSame( array( 123, 'class-existing' ), $GLOBALS['mock_async_actions'][0]['args'] );
		}
	}
}
