<?php
/**
 * Phase 4 smoke — subjects upsert + enrol + stale on stub data only.
 *
 * Does NOT call Univer, does NOT use real passwords, does NOT disable HTTPS-gate.
 * Safe on production HTTPS: php local/kaznu/cli/phase4_smoke.php
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');

use local_kaznu\api\dto\subject;
use local_kaznu\local\course_idnumber;
use local_kaznu\service\subject_service;
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

$prefix = 'p4smoke_' . time();
$userid = 0;
$courseid = 0;

try {
    $user = (object) [
        'auth' => 'nologin',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'username' => $prefix . '_u',
        'password' => hash_internal_user_password(random_string(16)),
        'firstname' => 'P4',
        'lastname' => 'Smoke',
        'email' => $prefix . '@users.invalid',
        'emailstop' => 1,
    ];
    $userid = user_create_user($user, false, false);

    // Unique synthetic Univer id — avoid fixed-key collisions across smoke runs.
    $univeruserid = (int) (900000000 + (time() % 100000000));
    $DB->insert_record('local_kaznu_univer_user', (object) [
        'userid' => $userid,
        'univer_userid' => $univeruserid,
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

    $idnum = course_idnumber::format(601, 2025, 1);
    // Unique idnumber if a previous failed smoke left a course.
    if ($DB->record_exists('course', ['idnumber' => $idnum])) {
        $idnum = course_idnumber::format(601, 2025, 1) . ':smoke' . time();
        // Mapping for enrol looks up exact course_idnumber from subject DTO, so update after or use free id.
        // Prefer freeing stub subject id range with unique year used only by smoke.
        $idnum = course_idnumber::format(601, 2099, 9);
    }

    $smokeYear = 2099;
    $smokeSem = 9;
    $idnum = course_idnumber::format(601, $smokeYear, $smokeSem);

    // Drop leftover test course with same idnumber (from aborted smoke).
    if ($old = $DB->get_record('course', ['idnumber' => $idnum])) {
        delete_course($old->id, false);
    }

    $course = create_course((object) [
        'fullname' => 'P4 ' . $prefix,
        'shortname' => 'P4' . substr($prefix, -10),
        'idnumber' => $idnum,
        'category' => 1,
    ]);
    $courseid = (int) $course->id;

    $svc = new subject_service();
    $s1 = new subject(601, 'Math', 1, 1, $smokeYear, $smokeSem, 1, 'L', 0, 1);
    $s2 = new subject(602, 'Phys', 1, 1, $smokeYear, $smokeSem, 1, 'L', 0, 1);

    $r1 = $svc->sync_dtos($userid, $smokeYear, $smokeSem, [$s1, $s2]);
    assert_true($r1['upserted'] === 2, 'first sync upserted 2');
    assert_true(is_enrolled(context_course::instance($courseid), $userid, '', true), 'enrolled when course found');
    assert_true($r1['not_found'] >= 1, 'missing course for Physics');

    $r2 = $svc->sync_dtos($userid, $smokeYear, $smokeSem, [$s1]);
    assert_true($r2['staled'] === 1, 'physics staled');
    assert_true(is_enrolled(context_course::instance($courseid), $userid, '', true), 'stale does not unenrol');
    $row = $DB->get_record('local_kaznu_univer_subject', ['userid' => $userid, 'subjectid' => 602]);
    assert_true($row && $row->status === univer_status::STALE, 'status stale');

    $r3 = $svc->sync_dtos($userid, $smokeYear, $smokeSem, [$s1, $s2]);
    assert_true($r3['reactivated'] === 1, 'reactivated');

    $cab = $svc->get_cabinet_data($userid);
    assert_true(count($cab['subjects']) === 2, 'cabinet from cache');
    assert_true($DB->record_exists('local_kaznu_sync_log', ['userid' => $userid]), 'sync_log rows');
} catch (Throwable $e) {
    echo 'ERROR ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    if (!empty($e->debuginfo)) {
        echo 'debuginfo: ' . $e->debuginfo . "\n";
    }
    $failed++;
} finally {
    if ($userid) {
        try {
            (new user_service())->purge_for_userid($userid);
        } catch (Throwable $e) {
            // Best effort.
        }
        try {
            if ($u = $DB->get_record('user', ['id' => $userid])) {
                delete_user($u);
            }
        } catch (Throwable $e) {
            // Best effort.
        }
    }
    if ($courseid) {
        try {
            delete_course($courseid, false);
        } catch (Throwable $e) {
            // Best effort.
        }
    }
}

echo "\nResult: $passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
