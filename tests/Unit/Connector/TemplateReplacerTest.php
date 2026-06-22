<?php
declare(strict_types=1);

namespace CertPSU\Connector\Tests\Unit\Connector;

use PHPUnit\Framework\TestCase;
use CertPSU\Connector\Utils\Template_Replacer;

class TemplateReplacerTest extends TestCase {

	private Template_Replacer $replacer;

	protected function setUp(): void {
		parent::setUp();
		$this->replacer = new Template_Replacer();
	}

	public function test_replace_single_placeholder(): void {
		$template = 'Hello {name}';
		$context  = array( 'name' => 'World' );
		
		$this->assertSame( 'Hello World', $this->replacer->replace( $template, $context ) );
	}

	public function test_replace_multiple_placeholders(): void {
		$template = '{greeting} {name}, your score is {score}';
		$context  = array(
			'greeting' => 'Hello',
			'name'     => 'Alice',
			'score'    => 100,
		);
		
		$this->assertSame( 'Hello Alice, your score is 100', $this->replacer->replace( $template, $context ) );
	}

	public function test_replace_leaves_unmatched_placeholders(): void {
		$template = 'Hello {name}, where is {missing}?';
		$context  = array( 'name' => 'Bob' );
		
		$this->assertSame( 'Hello Bob, where is {missing}?', $this->replacer->replace( $template, $context ) );
	}

	public function test_replace_recursive(): void {
		$data = array(
			'title'       => 'Certificate for {course_name}',
			'description' => 'Awarded to {student_name}',
			'metadata'    => array(
				'score' => '{score}',
				'date'  => '{date}',
				'nested' => array(
					'id' => '{id}'
				)
			),
			'number' => 123, // Non-string value
		);

		$context = array(
			'course_name'  => 'Math 101',
			'student_name' => 'John Doe',
			'score'        => '99',
			'date'         => '2023-01-01',
			'id'           => 'ID_999'
		);

		$expected = array(
			'title'       => 'Certificate for Math 101',
			'description' => 'Awarded to John Doe',
			'metadata'    => array(
				'score' => '99',
				'date'  => '2023-01-01',
				'nested' => array(
					'id' => 'ID_999'
				)
			),
			'number' => 123,
		);

		$this->assertSame( $expected, $this->replacer->replace_recursive( $data, $context ) );
	}

	public function test_replace_recursive_empty_context(): void {
		$data = array( 'key' => '{value}' );
		$this->assertSame( $data, $this->replacer->replace_recursive( $data, array() ) );
	}
}
