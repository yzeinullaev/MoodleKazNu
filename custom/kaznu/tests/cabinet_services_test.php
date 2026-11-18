<?php
namespace local_kaznu;

use local_kaznu\api\dto\assessment_item;
use local_kaznu\api\dto\attendance_item;
use local_kaznu\api\dto\exam_item;
use local_kaznu\api\dto\schedule_item;
use local_kaznu\api\univer_exception;
use local_kaznu\cache_module;
use local_kaznu\local\univer_datetime;
use local_kaznu\repository\assessment_repository;
use local_kaznu\repository\attendance_repository;
use local_kaznu\repository\cache_state_repository;
use local_kaznu\repository\schedule_repository;
use local_kaznu\service\assessment_service;
use local_kaznu\service\attendance_service;
use local_kaznu\service\schedule_service;
use local_kaznu\service\student_dashboard_service;
use local_kaznu\univer_status;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_kaznu\service\schedule_service
 * @covers \local_kaznu\service\assessment_service
 * @covers \local_kaznu\service\attendance_service
 */
class cabinet_services_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    private function link_user(): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['auth' => 'nologin']);
        $DB->insert_record('local_kaznu_univer_user', (object) [
            'userid' => $user->id,
            'univer_userid' => 910000 + (int) $user->id,
            'univer_login' => $user->username,
            'faculty' => 'F',
            'speciality' => 'S',
            'course_number' => 1,
            'group_name' => 'G',
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

    public function test_schedule_first_resync_and_replace(): void {
        $user = $this->link_user();
        $svc = new schedule_service();
        $a = new schedule_item(1, 1, 'Math', 'Лекция', 'T', 'Fac', '101', '10:00', '10:50', '01.09.2025', '13.12.2025', 0);
        $n = $svc->sync_dtos((int) $user->id, cache_module::SCHEDULE_TYPE_LESSON, 2025, 1, [$a, $a]);
        $this->assertSame(1, $n);
        $b = new schedule_item(1, 1, 'Math', 'Лекция', 'T', 'Fac', '202', '10:00', '10:50', '01.09.2025', '13.12.2025', 0);
        $svc->sync_dtos((int) $user->id, cache_module::SCHEDULE_TYPE_LESSON, 2025, 1, [$b]);
        $rows = $svc->get_lessons((int) $user->id, 2025, 1);
        $this->assertCount(1, $rows);
        $this->assertSame('202', $rows[0]->audience);
    }

    public function test_exam_midterm_separated(): void {
        $user = $this->link_user();
        $svc = new schedule_service();
        $svc->sync_dtos((int) $user->id, cache_module::SCHEDULE_TYPE_EXAM, 2025, 1, [
            new exam_item('Math', 'Экзамен', 'T', 'F', 'A', '15.12.25', '13:00'),
        ]);
        $svc->sync_dtos((int) $user->id, cache_module::SCHEDULE_TYPE_MIDTERM, 2025, 1, [
            new exam_item('Math', 'РК1', 'T', 'F', 'A', '01.11.25', '10:00'), // wrong type class - use midterm
        ]);
        // Use midterm DTO
        $svc->sync_dtos((int) $user->id, cache_module::SCHEDULE_TYPE_MIDTERM, 2025, 1, [
            new \local_kaznu\api\dto\midterm_item('Physics', 'РК1', 'T', 'F', 'B', '01.11.25', '10:00'),
        ]);
        $this->assertCount(1, $svc->get_exams((int) $user->id, 2025, 1));
        $this->assertCount(1, $svc->get_midterms((int) $user->id, 2025, 1));
        $this->assertSame('Physics', $svc->get_midterms((int) $user->id, 2025, 1)[0]->subjectname);
    }

    public function test_timezone_parse(): void {
        $ts = univer_datetime::parse_date('01.09.2025');
        $this->assertGreaterThan(0, $ts);
        $short = univer_datetime::parse_date('15.12.25');
        $this->assertGreaterThan(0, $short);
        $this->assertSame(0, univer_datetime::parse_date(''));
    }

    public function test_assessment_zero_and_idempotent(): void {
        global $DB;
        $user = $this->link_user();
        $svc = new assessment_service();
        $item = new assessment_item('Math', '3', '0', '0', '0', '0', '0', 'F', 'fail');
        $this->assertTrue($item->has_result());
        $svc->sync_dtos((int) $user->id, 2025, 1, [$item]);
        $svc->sync_dtos((int) $user->id, 2025, 1, [$item]);
        $this->assertEquals(1, $DB->count_records('local_kaznu_assessment', ['userid' => $user->id]));
        $this->assertSame('0', $svc->get_for_user((int) $user->id, 2025, 1)[0]->result);
        // Gradebook unchanged.
        $this->assertEquals(0, $DB->count_records('grade_grades', ['userid' => $user->id]));
    }

    public function test_attendance_and_error_keeps_cache(): void {
        global $DB;
        $user = $this->link_user();
        $svc = new attendance_service();
        $svc->sync_dtos((int) $user->id, 2025, 1, [
            new attendance_item('Math', 'formula', '01.09-13.12', 'g1', '93', '92'),
        ]);
        $this->assertEquals(1, $DB->count_records('local_kaznu_attendance', ['userid' => $user->id]));

        // Simulating live failure: mark failure without replace.
        (new cache_state_repository())->mark_failure(
            (int) $user->id,
            cache_module::ATTENDANCE,
            2025,
            1,
            'univer_server_error'
        );
        $this->assertEquals(1, $DB->count_records('local_kaznu_attendance', ['userid' => $user->id]));
        $fresh = (new cache_state_repository())->freshness((int) $user->id, cache_module::ATTENDANCE, 2025, 1, 1800);
        $this->assertSame('unavailable', $fresh);
    }

    public function test_dashboard_only_cache_and_own_user(): void {
        $user = $this->link_user();
        $other = $this->link_user();
        (new schedule_service())->sync_dtos((int) $user->id, cache_module::SCHEDULE_TYPE_LESSON, 2025, 1, [
            new schedule_item(2, 1, 'OnlyMine', 'L', '', '', '', '9:00', '9:50', '', '', 0),
        ]);
        $cab = (new student_dashboard_service())->get_cabinet((int) $user->id, 'schedule');
        $this->assertCount(1, $cab['lessons']);
        $cab2 = (new student_dashboard_service())->get_cabinet((int) $other->id, 'schedule');
        $this->assertCount(0, $cab2['lessons']);
    }
}
