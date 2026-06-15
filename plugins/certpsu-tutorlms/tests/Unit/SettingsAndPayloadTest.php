<?php
/**
 * Unit tests for TutorLMS settings sanitization and class payload building.
 *
 * Self-contained: requires the classes directly and stubs the few WordPress
 * functions used, so it runs without a full WordPress test environment.
 *
 * @package CertPSU\TutorLMS\Tests
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS\Tests\Unit;

use CertPSU\TutorLMS\Issuance\Class_Payload_Builder;
use CertPSU\TutorLMS\Settings\Settings_Sanitizer;
use PHPUnit\Framework\TestCase;

// WordPress function stubs (sanitize_text_field, sanitize_textarea_field,
// get_the_title) are provided globally by tests/bootstrap.php.
$root = dirname( __DIR__, 2 );
require_once $root . '/includes/Settings/Course_Settings.php';
require_once $root . '/includes/Settings/Settings_Sanitizer.php';
require_once $root . '/includes/Issuance/Class_Payload_Builder.php';

/**
 * Tests.
 */
final class SettingsAndPayloadTest extends TestCase {

	/**
	 * Sanitizer coerces types and applies defaults/whitelists.
	 *
	 * @return void
	 */
	public function test_sanitizer_normalizes_input(): void {
		$sanitizer = new Settings_Sanitizer();

		$clean = $sanitizer->sanitize(
			array(
				'enabled'                     => '1',
				'allow_duplicate_participant' => 'bogus',     // -> default not_allowed.
				'auto_release'                => '',           // -> false.
				'tags'                        => "a\nb\n\nc",  // -> list.
				'template_group'              => 'winner',
				'endorsers'                   => array(
					array( 'endorser_id' => 'e1', 'user' => 'u1', 'name' => 'Dr A' ),
					array( 'endorser_id' => '', 'user' => 'u2', 'name' => 'No id' ), // dropped.
				),
			)
		);

		self::assertTrue( $clean['enabled'] );
		self::assertFalse( $clean['auto_release'] );
		self::assertSame( 'not_allowed', $clean['allow_duplicate_participant'] );
		self::assertSame( array( 'a', 'b', 'c' ), $clean['tags'] );
		self::assertSame( 'winner', $clean['template_group'] );
		self::assertCount( 1, $clean['endorsers'] );
		self::assertSame( 'e1', $clean['endorsers'][0]['endorser_id'] );
		self::assertSame( 'required', $clean['endorsers'][0]['endorse_requirement'] );
	}

	/**
	 * Payload builder folds the template into the body and applies fallbacks.
	 *
	 * @return void
	 */
	public function test_payload_builder_builds_class_body(): void {
		$builder = new Class_Payload_Builder();

		$body = $builder->build(
			42,
			array(
				'certificate_email_template' => 'email-1',
				'class_name'                 => '',            // -> course title fallback.
				'started_date'               => '',           // -> today.
				'instructors'                => array( 'Teacher A' ), // avoids DB lookup.
				'tags'                       => array( 'x' ),
				'allow_duplicate_participant' => 'not_allowed',
				'auto_send_mail_participant' => 'auto',
				'endorse_method'             => 'auto',
				'template_id'                => 'tmpl-1',
				'template_group'             => 'participant',
			)
		);

		self::assertSame( 'email-1', $body['certificate_email_template'] );
		self::assertSame( 'Course 42', $body['class_']['name'] );
		self::assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $body['class_']['started_date'] );
		self::assertSame( array( 'Teacher A' ), $body['class_']['instructors'] );
		self::assertArrayHasKey( 'certificate_templates', $body );
		self::assertSame( 'tmpl-1', $body['certificate_templates'][0]['template'] );
		self::assertSame( 'Course 42', $body['certificate_templates'][0]['name'] );
	}

	/**
	 * No template id -> no certificate_templates key.
	 *
	 * @return void
	 */
	public function test_payload_builder_omits_template_when_unset(): void {
		$builder = new Class_Payload_Builder();
		$body    = $builder->build(
			7,
			array(
				'instructors' => array( 'T' ),
				'template_id' => '',
			)
		);
		self::assertArrayNotHasKey( 'certificate_templates', $body );
	}
}
