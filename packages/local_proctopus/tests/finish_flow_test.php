<?php
// This file is part of Moodle - http://moodle.org/

namespace local_proctopus;

use local_proctopus\service\proctoring_session_reconciler;
use local_proctopus\service\session_service;

defined('MOODLE_INTERNAL') || die();

/**
 * Finish flow + session reconcile (no Proctopus network, no JWT secrets).
 *
 * @covers \local_proctopus\service\proctoring_session_reconciler
 * @covers \local_proctopus\observer
 */
class finish_flow_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('proctopus_enabled', 1, 'local_proctopus');
        set_config('proctopus_live_calls_enabled', 0, 'local_proctopus');
        set_config('proctopus_legacy_mode', 1, 'local_proctopus');
    }

    private function make_session(array $over = []): \stdClass {
        global $DB;
        $now = time();
        $id = $DB->insert_record('local_proctopus_session', (object) array_merge([
            'userid' => 2,
            'courseid' => 1,
            'cmid' => 201,
            'quizid' => 22,
            'attemptid' => null,
            'external_session_id' => 'fps_test_' . bin2hex(random_bytes(4)),
            'status' => 'admitted',
            'proctoring_mode' => 'online',
            'started_at' => $now,
            'expires_at' => $now + 3600,
            'completed_at' => 0,
            'last_callback_at' => 0,
            'result_code' => '',
            'result_summary' => 'admitted',
            'correlation_id' => bin2hex(random_bytes(4)),
            'timecreated' => $now,
            'timemodified' => $now,
        ], $over));
        return $DB->get_record('local_proctopus_session', ['id' => $id], '*', MUST_EXIST);
    }

    public function test_finished_attempt_closes_admitted_session(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_proctopus_session')) {
            $this->markTestSkipped('table missing');
        }
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quizgen = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgen->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);

        $attempt = $quizgen->create_attempt($quiz, 1, false, $user->id);
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attempt->id], '*', MUST_EXIST);
        // Force finished.
        $attempt->state = 'finished';
        $attempt->timefinish = time();
        $attempt->sumgrades = 1;
        $DB->update_record('quiz_attempts', $attempt);

        $sess = $this->make_session([
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'quizid' => $quiz->id,
            'attemptid' => $attempt->id,
            'status' => 'admitted',
        ]);

        $r = proctoring_session_reconciler::on_attempt_finished(
            (int) $user->id,
            (int) $quiz->id,
            (int) $cm->id,
            (int) $attempt->id
        );
        $this->assertGreaterThanOrEqual(1, $r['completed']);
        $this->assertSame(0, $r['remaining_open']);
        $after = $DB->get_record('local_proctopus_session', ['id' => $sess->id]);
        $this->assertContains($after->status, ['completed', 'completed_pending_result']);
        $this->assertNotEmpty($after->completed_at);
        $this->assertEquals($attempt->id, (int) $after->attemptid);
    }

    public function test_duplicate_finish_idempotent(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_proctopus_session')) {
            $this->markTestSkipped('table missing');
        }
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quizgen = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgen->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $attempt = $quizgen->create_attempt($quiz, 1, false, $user->id);
        $attempt->state = 'finished';
        $attempt->timefinish = time();
        $DB->update_record('quiz_attempts', $attempt);

        $sess = $this->make_session([
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'quizid' => $quiz->id,
            'attemptid' => $attempt->id,
        ]);

        proctoring_session_reconciler::on_attempt_finished((int) $user->id, (int) $quiz->id, (int) $cm->id, (int) $attempt->id);
        $mid = $DB->get_record('local_proctopus_session', ['id' => $sess->id]);
        proctoring_session_reconciler::on_attempt_finished((int) $user->id, (int) $quiz->id, (int) $cm->id, (int) $attempt->id);
        $after = $DB->get_record('local_proctopus_session', ['id' => $sess->id]);
        $this->assertEquals($mid->status, $after->status);
        $this->assertEquals($mid->completed_at, $after->completed_at);
        $this->assertSame(0, proctoring_session_reconciler::count_open((int) $user->id, (int) $cm->id));
        $this->assertEquals(1, $DB->count_records('local_proctopus_session', ['id' => $sess->id]));
    }

    public function test_wrong_userid_cannot_complete_session(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_proctopus_session')) {
            $this->markTestSkipped('table missing');
        }
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quizgen = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgen->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $attempt = $quizgen->create_attempt($quiz, 1, false, $owner->id);
        $attempt->state = 'finished';
        $attempt->timefinish = time();
        $DB->update_record('quiz_attempts', $attempt);

        $sess = $this->make_session([
            'userid' => $owner->id,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'quizid' => $quiz->id,
            'attemptid' => $attempt->id,
        ]);

        // Observer path uses attempt owner — calling with wrong user leaves open.
        proctoring_session_reconciler::on_attempt_finished(
            (int) $other->id,
            (int) $quiz->id,
            (int) $cm->id,
            (int) $attempt->id
        );
        $after = $DB->get_record('local_proctopus_session', ['id' => $sess->id]);
        $this->assertSame('admitted', $after->status);
    }

    public function test_finished_leaves_zero_active_and_no_admit_ui(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_proctopus_session')) {
            $this->markTestSkipped('table missing');
        }
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quizgen = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgen->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $attempt = $quizgen->create_attempt($quiz, 1, false, $user->id);
        $attempt->state = 'finished';
        $attempt->timefinish = time();
        $DB->update_record('quiz_attempts', $attempt);

        $this->make_session([
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'quizid' => $quiz->id,
            'attemptid' => $attempt->id,
            'status' => 'admitted',
        ]);
        $this->make_session([
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'quizid' => $quiz->id,
            'attemptid' => null,
            'status' => 'started',
            'external_session_id' => 'fps_dup_' . bin2hex(random_bytes(3)),
        ]);

        proctoring_session_reconciler::on_attempt_finished((int) $user->id, (int) $quiz->id, (int) $cm->id, (int) $attempt->id);
        $this->assertSame(0, proctoring_session_reconciler::count_open((int) $user->id, (int) $cm->id));

        $svc = new session_service();
        $this->assertFalse($svc->user_has_admitted_session((int) $user->id, (int) $cm->id));
        $this->assertFalse(local_proctopus_user_has_inprogress_attempt((int) $user->id, (int) $cm->id));
        $this->assertSame('', local_proctopus_maybe_render_attempt_indicator((int) $cm->id, (int) $user->id));
    }

    public function test_new_session_not_reusing_completed(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_proctopus_session')) {
            $this->markTestSkipped('table missing');
        }
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quizgen = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgen->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);

        $old = $this->make_session([
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'quizid' => $quiz->id,
            'status' => 'completed',
            'completed_at' => time(),
            'result_summary' => 'moodle_quiz_finished',
        ]);

        $repo = new \local_proctopus\repository\session_repository();
        $active = $repo->get_active_for_user_cm((int) $user->id, (int) $cm->id);
        $this->assertNull($active);

        $fresh = $this->make_session([
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'quizid' => $quiz->id,
            'status' => 'created',
            'external_session_id' => 'fps_new_' . bin2hex(random_bytes(3)),
        ]);
        $active2 = $repo->get_active_for_user_cm((int) $user->id, (int) $cm->id);
        $this->assertNotNull($active2);
        $this->assertEquals($fresh->id, $active2->id);
        $this->assertNotEquals($old->id, $active2->id);
    }

    public function test_stale_redirected_expires_and_inprogress_kept(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_proctopus_session')) {
            $this->markTestSkipped('table missing');
        }
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quizgen = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgen->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);

        $liveattempt = $quizgen->create_attempt($quiz, 1, false, $user->id);
        // Ensure inprogress.
        $liveattempt->state = 'inprogress';
        $liveattempt->timefinish = 0;
        $DB->update_record('quiz_attempts', $liveattempt);

        $live = $this->make_session([
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'quizid' => $quiz->id,
            'attemptid' => $liveattempt->id,
            'status' => 'admitted',
            'expires_at' => time() + 3600,
        ]);
        $stale = $this->make_session([
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'quizid' => $quiz->id,
            'status' => 'started',
            'expires_at' => time() - 10,
            'external_session_id' => 'fps_stale_' . bin2hex(random_bytes(3)),
            'timemodified' => time() - 7200,
        ]);

        $plan = proctoring_session_reconciler::reconcile((int) $user->id, (int) $cm->id, null, true);
        $this->assertNotEmpty($plan);
        $afterlive = $DB->get_record('local_proctopus_session', ['id' => $live->id]);
        $afterstale = $DB->get_record('local_proctopus_session', ['id' => $stale->id]);
        $this->assertSame('admitted', $afterlive->status);
        $this->assertSame('expired', $afterstale->status);
    }

    public function test_callback_after_finish_updates_result(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_proctopus_session')) {
            $this->markTestSkipped('table missing');
        }
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quizgen = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgen->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $attempt = $quizgen->create_attempt($quiz, 1, false, $user->id);
        $attempt->state = 'finished';
        $attempt->timefinish = time();
        $DB->update_record('quiz_attempts', $attempt);

        $sess = $this->make_session([
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'quizid' => $quiz->id,
            'attemptid' => $attempt->id,
            'status' => 'admitted',
        ]);
        proctoring_session_reconciler::on_attempt_finished((int) $user->id, (int) $quiz->id, (int) $cm->id, (int) $attempt->id);
        $pending = $DB->get_record('local_proctopus_session', ['id' => $sess->id]);
        $this->assertSame('completed_pending_result', $pending->status);

        proctoring_session_reconciler::apply_provider_result($pending, 'ok', 'provider_done');
        $done = $DB->get_record('local_proctopus_session', ['id' => $sess->id]);
        $this->assertSame('completed', $done->status);
        $this->assertSame('ok', $done->result_code);
        // History preserved.
        $this->assertEquals(1, $DB->count_records('local_proctopus_session', ['id' => $sess->id]));
    }

    public function test_no_callback_still_terminal_for_ux(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_proctopus_session')) {
            $this->markTestSkipped('table missing');
        }
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quizgen = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgen->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $attempt = $quizgen->create_attempt($quiz, 1, false, $user->id);
        $attempt->state = 'finished';
        $attempt->timefinish = time();
        $DB->update_record('quiz_attempts', $attempt);

        $sess = $this->make_session([
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'quizid' => $quiz->id,
            'attemptid' => $attempt->id,
        ]);
        proctoring_session_reconciler::on_attempt_finished((int) $user->id, (int) $quiz->id, (int) $cm->id, (int) $attempt->id);
        $after = $DB->get_record('local_proctopus_session', ['id' => $sess->id]);
        $this->assertContains($after->status, ['completed', 'completed_pending_result']);
        $this->assertSame(0, proctoring_session_reconciler::count_open((int) $user->id, (int) $cm->id));
        $this->assertFalse((new session_service())->user_has_admitted_session((int) $user->id, (int) $cm->id));
    }

    public function test_reconcile_dry_run_changes_nothing(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_proctopus_session')) {
            $this->markTestSkipped('table missing');
        }
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quizgen = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgen->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $sess = $this->make_session([
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'quizid' => $quiz->id,
            'status' => 'started',
            'expires_at' => time() - 5,
        ]);
        $before = $DB->get_record('local_proctopus_session', ['id' => $sess->id]);
        $plan = proctoring_session_reconciler::reconcile((int) $user->id, (int) $cm->id, null, false);
        $this->assertNotEmpty($plan);
        $after = $DB->get_record('local_proctopus_session', ['id' => $sess->id]);
        $this->assertEquals($before->status, $after->status);
        $this->assertEquals($before->timemodified, $after->timemodified);

        $plan2 = proctoring_session_reconciler::reconcile((int) $user->id, (int) $cm->id, null, true);
        $this->assertTrue($plan2[0]['applied']);
        $closed = $DB->get_record('local_proctopus_session', ['id' => $sess->id]);
        $this->assertSame('expired', $closed->status);
    }

    public function test_session_history_no_delete_on_finish(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_proctopus_session')) {
            $this->markTestSkipped('table missing');
        }
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quizgen = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgen->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $attempt = $quizgen->create_attempt($quiz, 1, false, $user->id);
        $attempt->state = 'finished';
        $attempt->timefinish = time();
        $DB->update_record('quiz_attempts', $attempt);

        $s1 = $this->make_session([
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'quizid' => $quiz->id,
            'attemptid' => $attempt->id,
        ]);
        $s2 = $this->make_session([
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'quizid' => $quiz->id,
            'status' => 'started',
            'external_session_id' => 'fps_x_' . bin2hex(random_bytes(3)),
        ]);
        $countbefore = $DB->count_records('local_proctopus_session', ['userid' => $user->id, 'cmid' => $cm->id]);
        proctoring_session_reconciler::on_attempt_finished((int) $user->id, (int) $quiz->id, (int) $cm->id, (int) $attempt->id);
        $countafter = $DB->count_records('local_proctopus_session', ['userid' => $user->id, 'cmid' => $cm->id]);
        $this->assertEquals($countbefore, $countafter);
        $this->assertTrue($DB->record_exists('local_proctopus_session', ['id' => $s1->id]));
        $this->assertTrue($DB->record_exists('local_proctopus_session', ['id' => $s2->id]));
    }

    public function test_observer_submitted_closes_session(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_proctopus_session')) {
            $this->markTestSkipped('table missing');
        }
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quizgen = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgen->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $attempt = $quizgen->create_attempt($quiz, 1, false, $user->id);
        $attempt->state = 'finished';
        $attempt->timefinish = time();
        $DB->update_record('quiz_attempts', $attempt);

        $sess = $this->make_session([
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'quizid' => $quiz->id,
            'attemptid' => $attempt->id,
        ]);

        // Direct observer entry (event object is heavy to mock fully).
        $sink = $this->redirectEvents();
        // Call private via submit path function.
        $ref = new \ReflectionClass(observer::class);
        $m = $ref->getMethod('finish_from_attempt_id');
        $m->setAccessible(true);
        $m->invoke(null, (int) $attempt->id);
        $m->invoke(null, (int) $attempt->id);
        $after = $DB->get_record('local_proctopus_session', ['id' => $sess->id]);
        $this->assertContains($after->status, ['completed', 'completed_pending_result']);
        $sink->close();
    }

    public function test_attempt_started_links_session(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_proctopus_session')) {
            $this->markTestSkipped('table missing');
        }
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quizgen = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgen->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $attempt = $quizgen->create_attempt($quiz, 1, false, $user->id);
        $sess = $this->make_session([
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'quizid' => $quiz->id,
            'attemptid' => null,
            'status' => 'admitted',
        ]);
        proctoring_session_reconciler::on_attempt_started(
            (int) $user->id,
            (int) $quiz->id,
            (int) $cm->id,
            (int) $attempt->id
        );
        $after = $DB->get_record('local_proctopus_session', ['id' => $sess->id]);
        $this->assertEquals($attempt->id, (int) $after->attemptid);
    }
}
