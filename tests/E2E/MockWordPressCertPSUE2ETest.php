<?php
/**
 * Offline E2E simulation: Tutor completion -> Action Scheduler -> CertPSU.
 *
 * WordPress storage, course, student, queue, API and certificate are fixtures.
 * No real WordPress installation, database or external HTTP is used.
 */
declare(strict_types=1);

namespace CertPSU\Connector\Tests\E2E;

use CertPSU\Connector\CertPSU\Api_Response;
use CertPSU\Connector\CertPSU\CertPSU_Api_Client;
use CertPSU\TutorLMS\Issuance\Completion_Handler;
use CertPSU\TutorLMS\Issuance\Course_Class_Manager;
use CertPSU\TutorLMS\Listener;
use CertPSU\TutorLMS\Settings\Course_Settings;
use CertPSU\TutorLMS\Support\Learner;
use PHPUnit\Framework\TestCase;

final class MockWordPressCertPSUE2ETest extends TestCase {
    private const COURSE_ID = 91001;
    private const USER_ID = 91002;

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['mock_post_meta'] = array();
        $GLOBALS['mock_user_meta'] = array();
        $GLOBALS['mock_async_actions'] = array();
        $GLOBALS['mock_scheduled_actions'] = array();
        $GLOBALS['mock_options'] = array();
        $GLOBALS['mock_transients'] = array();
        unset($GLOBALS['mock_certpsu_api']);
    }

    private function configureCourse(bool $enabled = true): void {
        $GLOBALS['mock_post_meta'][self::COURSE_ID][Course_Settings::META_KEY] = array(
            'enabled' => $enabled,
            'auto_release' => true,
            'template_id' => 'fixture-template-001',
            'template_group' => 'participant',
        );
    }

    private function fakeHandler(CertPSU_Api_Client $api, bool $shouldCall = true): Completion_Handler {
        $classes = $this->createMock(Course_Class_Manager::class);
        $learner = $this->createMock(Learner::class);
        $learner->expects($shouldCall ? $this->once() : $this->never())
            ->method('participant')->willReturn(array(
                'name' => 'E2E Mock Learner',
                'email' => 'learner@example.test',
                'common_id' => 'e2e-test-learner',
            ));
        $classes->expects($shouldCall ? $this->once() : $this->never())
            ->method('ensure_class')->willReturn('fixture-class-001');
        $GLOBALS['mock_certpsu_api'] = $api;
        return new Completion_Handler($classes, $learner);
    }

    public function test_complete_course_queues_job_releases_certificate_and_prevents_duplicates(): void {
        $this->configureCourse();
        (new Listener())->on_course_completed(self::COURSE_ID, self::USER_ID);
        $queue = $GLOBALS['mock_async_actions'];
        self::assertCount(1, $queue);
        self::assertSame(Completion_Handler::HOOK, $queue[0]['hook']);
        self::assertSame(Completion_Handler::GROUP, $queue[0]['group']);
        self::assertSame(self::COURSE_ID, $queue[0]['args'][0]['course_id']);
        self::assertSame(self::USER_ID, $queue[0]['args'][0]['user_id']);

        $api = $this->createMock(CertPSU_Api_Client::class);
        $api->expects($this->once())->method('add_participants')
            ->with('fixture-class-001', $this->callback(
                fn(array $payload): bool =>
                    ($payload['participants'][0]['email'] ?? '') === 'learner@example.test'
            ))
            ->willReturn(new Api_Response(true, 200, array(
                'participants' => array(array('id' => 'fixture-participant-001')),
            ), null, null, null, 1));
        $api->expects($this->once())->method('release_participant')
            ->with('fixture-class-001', 'fixture-participant-001')
            ->willReturn(new Api_Response(true, 200, array(
                'certificate_url' => 'https://example.test/certificates/mock-001',
            ), null, null, null, 2));
        $handler = $this->fakeHandler($api);
        $handler->handle($queue[0]['args'][0]);

        $record = (new \CertPSU\TutorLMS\Issuance\Learner_State_Repository())->get(self::USER_ID,self::COURSE_ID);
        self::assertTrue($record['released'] ?? false);
        self::assertSame('fixture-class-001', $record['class_id']);
        self::assertSame('fixture-participant-001', $record['participant_id']);
        self::assertSame('https://example.test/certificates/mock-001', $record['certificate_url']);

        // Reprocessing must not contact the API again or modify persisted data.
        $before = $GLOBALS['mock_user_meta'];
        $noCalls = $this->createMock(CertPSU_Api_Client::class);
        $noCalls->expects($this->never())->method('add_participants');
        $noCalls->expects($this->never())->method('release_participant');
        $this->fakeHandler($noCalls, false)->handle($queue[0]['args'][0]);
        self::assertSame($before, $GLOBALS['mock_user_meta']);
    }

    public function test_disabled_course_does_not_queue_certificate(): void {
        $this->configureCourse(false);
        (new Listener())->on_course_completed(self::COURSE_ID, self::USER_ID);
        self::assertSame(array(), $GLOBALS['mock_async_actions']);
    }

    public function test_api_failure_schedules_retry_without_falsely_releasing(): void {
        $this->configureCourse();
        $api = $this->createMock(CertPSU_Api_Client::class);
        $api->expects($this->once())->method('add_participants')
            ->willReturn(new Api_Response(false, 503, null, null, 'unavailable', 'API unavailable', 3));
        $api->expects($this->never())->method('release_participant');

        $this->fakeHandler($api)->handle(array(
            'course_id' => self::COURSE_ID, 'user_id' => self::USER_ID, 'attempt' => 1,
        ));
        self::assertCount(1, $GLOBALS['mock_scheduled_actions']);
        self::assertSame(Completion_Handler::HOOK, $GLOBALS['mock_scheduled_actions'][0]['hook']);
        self::assertSame(2, $GLOBALS['mock_scheduled_actions'][0]['args'][0]['attempt']);
        self::assertEmpty($GLOBALS['mock_user_meta'][self::USER_ID]['_certpsu_tutorlms_issued'] ?? array());
    }
}
