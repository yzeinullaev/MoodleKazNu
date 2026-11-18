<?php
namespace local_kaznu;

use local_kaznu\api\dto\subject;
use local_kaznu\local\course_idnumber;
use local_kaznu\service\enrolment_service;
use local_kaznu\service\subject_service;
use local_kaznu\univer_status;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_kaznu\service\subject_service
 * @covers \local_kaznu\service\enrolment_service
 */
class subject_service_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    private function dto(int $id, string $name, int $year = 2025, int $sem = 1): subject {
        return new subject($id, $name, 10, 20, $year, $sem, 1, 'L', 0, 1);
    }

    private function user_with_link(): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['auth' => 'nologin']);
        $DB->insert_record('local_kaznu_univer_user', (object) [
            'userid' => $user->id,
            'univer_userid' => 900000 + (int) $user->id,
            'univer_login' => $user->username,
            'faculty' => 'Fac',
            'speciality' => 'Spec',
            'course_number' => 2,
            'group_name' => 'G1',
            'current_year' => 2025,
            'current_semester' => 1,
            'email_is_technical' => 1,
            'status' => univer_status::ACTIVE,
            'lastsync' => time(),
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        return $user;
    }

    private function course_with_idnumber(string $idnumber): \stdClass {
        return $this->getDataGenerator()->create_course([
            'idnumber' => $idnumber,
            'fullname' => 'Univer Course',
        ]);
    }

    public function test_first_sync_and_dedupe(): void {
        $user = $this->user_with_link();
        $svc = new subject_service();
        // Two rows with same unique key (lecture/seminar) → one cache row.
        $stats = $svc->sync_dtos((int) $user->id, 2025, 1, [
            $this->dto(501, 'Math L'),
            $this->dto(501, 'Math S'),
            $this->dto(502, 'Physics'),
        ]);
        $this->assertSame(2, $stats['upserted']);
        global $DB;
        $this->assertEquals(2, $DB->count_records('local_kaznu_univer_subject', ['userid' => $user->id]));
    }

    public function test_resync_no_duplicate(): void {
        $user = $this->user_with_link();
        $svc = new subject_service();
        $svc->sync_dtos((int) $user->id, 2025, 1, [$this->dto(1, 'A')]);
        $svc->sync_dtos((int) $user->id, 2025, 1, [$this->dto(1, 'A updated')]);
        global $DB;
        $this->assertEquals(1, $DB->count_records('local_kaznu_univer_subject', ['userid' => $user->id]));
        $row = $DB->get_record('local_kaznu_univer_subject', ['userid' => $user->id, 'subjectid' => 1]);
        $this->assertSame('A updated', $row->subjectname);
        $this->assertSame(univer_status::ACTIVE, $row->status);
    }

    public function test_stale_and_reactivate(): void {
        $user = $this->user_with_link();
        $svc = new subject_service();
        $svc->sync_dtos((int) $user->id, 2025, 1, [$this->dto(1, 'A'), $this->dto(2, 'B')]);
        $stats = $svc->sync_dtos((int) $user->id, 2025, 1, [$this->dto(1, 'A')]);
        $this->assertSame(1, $stats['staled']);
        global $DB;
        $b = $DB->get_record('local_kaznu_univer_subject', ['userid' => $user->id, 'subjectid' => 2]);
        $this->assertSame(univer_status::STALE, $b->status);
        $this->assertEquals(2, $DB->count_records('local_kaznu_univer_subject', ['userid' => $user->id]));

        $re = $svc->sync_dtos((int) $user->id, 2025, 1, [$this->dto(1, 'A'), $this->dto(2, 'B')]);
        $this->assertSame(1, $re['reactivated']);
        $b = $DB->get_record('local_kaznu_univer_subject', ['userid' => $user->id, 'subjectid' => 2]);
        $this->assertSame(univer_status::ACTIVE, $b->status);
    }

    public function test_course_found_and_enrol(): void {
        $user = $this->user_with_link();
        $idnumber = course_idnumber::format(77, 2025, 1);
        $course = $this->course_with_idnumber($idnumber);
        $svc = new subject_service();
        $stats = $svc->sync_dtos((int) $user->id, 2025, 1, [$this->dto(77, 'Algo')]);
        $this->assertGreaterThanOrEqual(1, $stats['enrolled']);
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user->id, '', true));
        global $DB;
        $row = $DB->get_record('local_kaznu_univer_subject', ['userid' => $user->id, 'subjectid' => 77]);
        $this->assertEquals($course->id, $row->moodlecourseid);
        $this->assertEquals(1, (int) $row->enrolled);
    }

    public function test_course_missing_and_already_enrolled(): void {
        $user = $this->user_with_link();
        $svc = new subject_service();
        $stats = $svc->sync_dtos((int) $user->id, 2025, 1, [$this->dto(88, 'Missing')]);
        $this->assertSame(1, $stats['not_found']);

        $idnumber = course_idnumber::format(99, 2025, 1);
        $course = $this->course_with_idnumber($idnumber);
        // Pre-enrol.
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $stats2 = $svc->sync_dtos((int) $user->id, 2025, 1, [$this->dto(99, 'Pre')]);
        $this->assertSame(0, $stats2['errors']);
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user->id, '', true));
        global $DB;
        // Only one enrolment row remains.
        $this->assertEquals(1, $DB->count_records_select(
            'user_enrolments',
            'userid = :u AND enrolid IN (SELECT id FROM {enrol} WHERE courseid = :c)',
            ['u' => $user->id, 'c' => $course->id]
        ));
    }

    public function test_stale_does_not_unenrol(): void {
        $user = $this->user_with_link();
        $idnumber = course_idnumber::format(55, 2025, 1);
        $course = $this->course_with_idnumber($idnumber);
        $svc = new subject_service();
        $svc->sync_dtos((int) $user->id, 2025, 1, [$this->dto(55, 'Stay')]);
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user->id, '', true));
        $svc->sync_dtos((int) $user->id, 2025, 1, []); // all stale
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user->id, '', true));
    }

    public function test_cabinet_data_from_cache(): void {
        $user = $this->user_with_link();
        $svc = new subject_service();
        $svc->sync_dtos((int) $user->id, 2025, 1, [$this->dto(1, 'CacheOnly')]);
        $cab = $svc->get_cabinet_data((int) $user->id);
        $this->assertNotEmpty($cab['profile']);
        $this->assertCount(1, $cab['subjects']);
        $this->assertSame('CacheOnly', $cab['subjects'][0]->subjectname);
    }

    public function test_sync_log_written(): void {
        $user = $this->user_with_link();
        (new subject_service())->sync_dtos((int) $user->id, 2025, 1, [$this->dto(1, 'Log')]);
        global $DB;
        $this->assertTrue($DB->record_exists('local_kaznu_sync_log', [
            'userid' => $user->id,
            'action' => 'subjects_sync',
        ]));
    }

    public function test_enrol_error_counted(): void {
        $user = $this->user_with_link();
        $enrol = $this->createMock(enrolment_service::class);
        $enrol->method('ensure_for_subject_row')->willReturn([
            'status' => 'error',
            'courseid' => 1,
            'enrolled' => false,
        ]);
        $enrol->method('reconcile_row');
        $svc = new subject_service(null, $enrol);
        $stats = $svc->sync_dtos((int) $user->id, 2025, 1, [$this->dto(1, 'Err')]);
        $this->assertSame(1, $stats['errors']);
    }
}
