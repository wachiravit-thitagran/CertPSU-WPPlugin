<?php
/**
 * Unit tests for Tutor_Course_Builder.
 *
 * @package CertPSU\TutorLMS\Tests
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS\Tests\Unit;

use CertPSU\TutorLMS\Integration\Tutor_Course_Builder;
use PHPUnit\Framework\TestCase;

$root = dirname( __DIR__, 2 );
require_once $root . '/includes/Settings/Course_Settings.php';
require_once $root . '/includes/Settings/Remote_Options.php';
require_once $root . '/includes/Integration/Tutor_Course_Builder.php';


/**
 * Tests.
 */
final class TutorCourseBuilderTest extends TestCase {

	/**
	 * Verify that the enqueue method correctly exposes the right JS variable names
	 * to the frontend builder, specifically the email templates, avoiding regressions
	 * where the JS expects one name (emailCertificate) and PHP sends another.
	 *
	 * @return void
	 */
	public function test_enqueue_localizes_correct_js_variables(): void {
		if ( ! defined( 'CERTPSU_TUTORLMS_URL' ) ) {
			define( 'CERTPSU_TUTORLMS_URL', 'http://test/' );
		}

		/** @var array<string, array<string, mixed>> $mock_localized_scripts */
		$mock_localized_scripts = array();
		$GLOBALS['mock_localized_scripts'] = $mock_localized_scripts;

		$builder = new Tutor_Course_Builder();
		$builder->enqueue();

		/** @var array<string, array<string, mixed>> $scripts */
		$scripts = $GLOBALS['mock_localized_scripts'];
		$data    = $scripts['certpsu-tutorlms-course-builder']['CertPSUCourseBuilder'] ?? array();

		// The JS builder expects these exact keys to populate the dropdowns.
		self::assertArrayHasKey( 'emailCertificate', $data, 'Must expose emailCertificate' );
		self::assertArrayHasKey( 'emailRequired', $data, 'Must expose emailRequired' );
		self::assertArrayHasKey( 'emailWithout', $data, 'Must expose emailWithout' );

		// The old buggy key 'emailParticipant' must not be reintroduced.
		self::assertArrayNotHasKey( 'emailParticipant', $data, 'Must not use the old emailParticipant key' );
	}
}
