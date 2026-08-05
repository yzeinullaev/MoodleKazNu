<?php
/**
 * Phase 5 smoke — cabinet cache modules on stub data (no Univer HTTP).
 * php local/kaznu/cli/phase5_smoke.php
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/user/lib.php');

use local_kaznu\api\dto\assessment_item;
use local_kaznu\api\dto\attendance_item;
use local_kaznu\api\dto\exam_item;
use local_kaznu\api\dto\midterm_item;
use local_kaznu\api\dto\schedule_item;
use local_kaznu\cache_module;
use local_kaznu\repository\cache_state_repository;
use local_kaznu\service\assessment_service;
use local_kaznu\service\attendance_service;
use local_kaznu\service\schedule_service;
use local_kaznu\service\student_dashboard_service;
use local_kaznu\service\user_service;
use local_kaznu\univer_status;

$passed = 0;
$failed = 0;
function assert_true($c, string $n): void {
    global $passed, $failed;
    if ($c) {
        echo "PASS  $n\n";
        $passed++;
    } else {
        echo "FAIL  $n\n";
        $failed++;
    }
}

global $CFG, $DB;

$prefix = 'p5smoke_' . time();
$userid = 0;
try {
    $user = (object) [
        'auth' => 'nologin',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'username' => $prefix . '_u',
        'password' => hash_internal_user_password(random_string(16)),
        'firstname' => 'P5',
        'lastname' => 'Smoke',
        'email' => $prefix . '@users.invalid',
        'emailstop' => 1,
    ];
    $userid = user_create_user($user, false, false);
    $DB->insert_record('local_kaznu_univer_user', (object) [
        'userid' => $userid,
        'univer_userid' => 920000000 + (time() % 1000000),
        'univer_login' => $prefix . '_u',
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

    $sched = new schedule_service();
    $nL = $sched->sync_dtos($userid, cache_module::SCHEDULE_TYPE_LESSON, 2025, 1, [
        new schedule_item(1, 1, 'Math', 'Лекция', 'T', 'Fac', '1', '10:00', '10:50', '01.09.2025', '13.12.2025', 0),
    ]);
    assert_true($nL === 1, 'schedule lessons');
    $nE = $sched->sync_dtos($userid, cache_module::SCHEDULE_TYPE_EXAM, 2025, 1, [
        new exam_item('Math', 'Экзамен', 'T', 'F', 'A', '15.12.25', '13:00'),
    ]);
    assert_true($nE === 1, 'exams');
    $nM = $sched->sync_dtos($userid, cache_module::SCHEDULE_TYPE_MIDTERM, 2025, 1, [
        new midterm_item('Math', 'РК', 'T', 'F', 'A', '01.11.25', '10:00'),
    ]);
    assert_true($nM === 1, 'midterms');

    $assess = new assessment_service();
    $nA = $assess->sync_dtos($userid, 2025, 1, [
        new assessment_item('Math', '3', '0', '0', '85', '85', '3,33', 'B+', 'хор.'),
    ]);
    assert_true($nA === 1, 'assessments');

    $att = new attendance_service();
    $nT = $att->sync_dtos($userid, 2025, 1, [
        new attendance_item('Math', 'f', '01.09-13.12', 'g1', '93', '92'),
    ]);
    assert_true($nT === 1, 'attendance');

    $cab = (new student_dashboard_service())->get_cabinet($userid, 'schedule');
    assert_true(count($cab['lessons']) === 1 && count($cab['exams']) === 1 && count($cab['midterms']) === 1, 'cabinet cache read');
    $cabA = (new student_dashboard_service())->get_cabinet($userid, 'assessments');
    $cabT = (new student_dashboard_service())->get_cabinet($userid, 'attendance');
    assert_true(count($cabA['assessments']) === 1 && count($cabT['attendance']) === 1, 'cabinet assessments/att');

    // Volume smoke: 60 lessons, 10 assessments, empty modules OK.
    $lessons = [];
    for ($i = 1; $i <= 60; $i++) {
        $day = (($i - 1) % 7) + 1;
        $lessons[] = new schedule_item($day, 1, "Subj $i 中文 Қазақ", 'L', 'T <b>x</b>', 'F', (string) $i, '10:00', '10:50', '01.09.2025', '13.12.2025', 0);
    }
    assert_true($sched->sync_dtos($userid, cache_module::SCHEDULE_TYPE_LESSON, 2025, 1, $lessons) === 60, '60 lessons upsert');
    $many = [];
    for ($i = 1; $i <= 10; $i++) {
        $many[] = new assessment_item("A$i", '3', '0', '0', (string) $i, (string) $i, '0', 'F', '');
    }
    assert_true($assess->sync_dtos($userid, 2025, 1, $many) === 10, '10 assessments');
    assert_true($att->sync_dtos($userid, 2025, 1, []) === 0, 'empty attendance snapshot ok');

    // Cleanup: keeps current semester (even if lastsynced old); purges other periods past retention.
    $sched->sync_dtos($userid, cache_module::SCHEDULE_TYPE_LESSON, 2020, 1, [
        new schedule_item(1, 1, 'Old', 'L', 'T', 'F', '9', '10:00', '10:50', '01.09.2020', '13.12.2020', 0),
    ]);
    $DB->set_field_select(
        'local_kaznu_schedule',
        'lastsynced',
        time() - (200 * DAYSECS),
        'userid = :u',
        ['u' => $userid]
    );
    $before = time() - (180 * DAYSECS);
    (new \local_kaznu\repository\schedule_repository())->purge_older_than($before);
    $cur = $DB->count_records('local_kaznu_schedule', [
        'userid' => $userid, 'itemtype' => 'lesson', 'academyear' => 2025, 'academsemester' => 1,
    ]);
    $old = $DB->count_records('local_kaznu_schedule', [
        'userid' => $userid, 'itemtype' => 'lesson', 'academyear' => 2020, 'academsemester' => 1,
    ]);
    assert_true($cur === 60, 'cleanup keeps current semester even if lastsynced old');
    assert_true($old === 0, 'cleanup purges non-current period older than retention');
    assert_true($DB->count_records('local_kaznu_schedule', ['userid' => $userid, 'itemtype' => 'lesson']) === 60, '60 lessons still present');

    assert_true(($cab['status'][cache_module::SCHEDULE]['freshness'] ?? '') === 'fresh', 'fresh status');

    // Error keeps last successful attendance.
    (new cache_state_repository())->mark_failure($userid, cache_module::ATTENDANCE, 2025, 1, 'univer_timeout');
    // after empty attendance snapshot we re-need rows
    $att->sync_dtos($userid, 2025, 1, [
        new attendance_item('Math', 'f', '01.09-13.12', 'g1', '93', '92'),
    ]);
    (new cache_state_repository())->mark_failure($userid, cache_module::ATTENDANCE, 2025, 1, 'univer_timeout');
    assert_true($DB->count_records('local_kaznu_attendance', ['userid' => $userid]) === 1, 'cache kept after error');
    assert_true((new cache_state_repository())->freshness($userid, cache_module::ATTENDANCE, 2025, 1, 60) === 'unavailable', 'unavailable label');

    // No gradebook side effects.
    assert_true($DB->count_records('grade_grades', ['userid' => $userid]) === 0, 'gradebook untouched');
} catch (Throwable $e) {
    echo 'ERROR ' . $e->getMessage() . "\n";
    $failed++;
} finally {
    if ($userid) {
        (new user_service())->purge_for_userid($userid);
        if ($u = $DB->get_record('user', ['id' => $userid])) {
            delete_user($u);
        }
    }
}

echo "\nResult: $passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
