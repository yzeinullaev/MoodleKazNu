<?php
/**
 * Phase 2 smoke scenarios (when PHPUnit is not installed on the host).
 *
 * php local/kaznu/cli/phase2_smoke.php
 */
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/user/lib.php');

use local_kaznu\api\dto\login_result;
use local_kaznu\api\dto\semester_info;
use local_kaznu\api\dto\student_profile;
use local_kaznu\api\dto\subject;
use local_kaznu\api\univer_exception;
use local_kaznu\repository\univer_subject_repository;
use local_kaznu\service\user_service;
use local_kaznu\univer_status;

class phase2_failing_subjects extends univer_subject_repository {
    public function sync_period(int $userid, int $year, int $semester, array $subjects): array {
        throw new RuntimeException('forced sync failure');
    }
}

$passed = 0;
$failed = 0;

/**
 * @param mixed $cond
 */
function assert_true($cond, string $name): void {
    global $passed, $failed;
    if ($cond) {
        echo "PASS  $name\n";
        $passed++;
    } else {
        echo "FAIL  $name\n";
        $failed++;
    }
}

global $CFG, $DB;

$svc = new user_service();
$prefix = 'p2smoke_' . time();

$login = new login_result(880001, $prefix . '_u1', 'STUDENT', 1, null, 'ok');
$profile = new student_profile(
    880001, $prefix . '_u1', 'Smoke', 'One', '', null, true,
    'Fac', 'Spec', 1, 'G1', 'Smoke', 'One'
);
$sem = new semester_info(2025, 1, 8, 'RK1');
$sub1 = new subject(501, 'Math', 10, 20, 2025, 1, 1, 'L', 0, 1);
$sub2 = new subject(502, 'Physics', 10, 20, 2025, 1, 1, 'L', 0, 1);

$r1 = $svc->upsert_from_univer($login, $profile, $sem, [$sub1, $sub2]);
assert_true($r1['created'] === true, 'create new user');
assert_true($r1['user']->email === 'univer_880001@users.invalid', 'technical email stub');
assert_true((int) $r1['user']->emailstop === 1, 'emailstop for technical email');
$userid = (int) $r1['user']->id;

$r2 = $svc->upsert_from_univer($login, $profile, $sem, [$sub1, $sub2]);
assert_true($r2['created'] === false && (int) $r2['user']->id === $userid, 'resync without duplicate');
assert_true($DB->count_records('local_kaznu_univer_user', ['univer_userid' => 880001]) === 1, 'single univer_user row');

set_config('univer_auth_update', 1, 'local_kaznu');
$profile2 = new student_profile(
    880001, $prefix . '_u1', 'Updated', 'Name', '', null, true,
    'Fac', 'Spec', 1, 'G1', 'Updated', 'Name'
);
$svc->upsert_from_univer($login, $profile2);
$user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
assert_true($user->firstname === 'Updated' && $user->lastname === 'Name', 'profile update when auth_update on');
set_config('univer_auth_update', 0, 'local_kaznu');
assert_true(str_ends_with($user->email, '@users.invalid'), 'missing email uses stub');

// Manual corrections preserved when univer_auth_update is off.
$user->firstname = 'Manual';
$user->lastname = 'Fix';
$user->email = $prefix . '_real@example.com';
$user->emailstop = 0;
user_update_user($user, false, false);
$DB->set_field('local_kaznu_univer_user', 'email_is_technical', 0, ['userid' => $userid]);
$svc->upsert_from_univer(
    $login,
    new student_profile(
        880001, $prefix . '_u1', 'FromUniver', 'API', '', null, true,
        'Fac', 'Spec', 1, 'G1', 'FromUniver', 'API'
    )
);
$user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
assert_true(
    $user->firstname === 'Manual' && $user->lastname === 'Fix' && $user->email === $prefix . '_real@example.com',
    'manual profile preserved when auth_update off'
);

$cu = (object) [
    'auth' => 'manual',
    'confirmed' => 1,
    'mnethostid' => $CFG->mnet_localhost_id,
    'username' => $prefix . '_taken',
    'password' => hash_internal_user_password(random_string(16)),
    'firstname' => 'T',
    'lastname' => 'U',
    'email' => $prefix . '_taken@example.com',
];
$cuid = user_create_user($cu, false, false);
$DB->insert_record('local_kaznu_univer_user', (object) [
    'userid' => $cuid,
    'univer_userid' => 770001,
    'univer_login' => $prefix . '_taken',
    'faculty' => '',
    'speciality' => '',
    'course_number' => 0,
    'group_name' => '',
    'current_year' => 0,
    'current_semester' => 0,
    'email_is_technical' => 0,
    'status' => univer_status::ACTIVE,
    'lastsync' => time(),
    'timecreated' => time(),
    'timemodified' => time(),
]);

$conflict = false;
try {
    $svc->upsert_from_univer(
        new login_result(990001, $prefix . '_taken', 'STUDENT', 1, null, 'ok'),
        new student_profile(990001, $prefix . '_taken', 'A', 'B', '', null, true, '', '', 0, '', '', '')
    );
} catch (univer_exception $e) {
    $conflict = ($e->errorcode === 'univer_username_conflict');
}
assert_true($conflict, 'username conflict');

$r3 = $svc->upsert_from_univer($login, $profile, $sem, [$sub1]);
assert_true($r3['subjects']['staled'] === 1, 'mark missing subject stale');
$phys = $DB->get_record('local_kaznu_univer_subject', ['userid' => $userid, 'subjectid' => 502]);
assert_true($phys && $phys->status === univer_status::STALE, 'physics row kept as stale');
assert_true($DB->count_records('local_kaznu_univer_subject', ['userid' => $userid]) === 2, 'subjects not deleted');

$r4 = $svc->upsert_from_univer($login, $profile, $sem, [$sub1, $sub2]);
assert_true($r4['subjects']['reactivated'] === 1, 'reactivate subject');
$phys = $DB->get_record('local_kaznu_univer_subject', ['userid' => $userid, 'subjectid' => 502]);
assert_true($phys && $phys->status === univer_status::ACTIVE, 'physics active again');

$failing = new user_service(null, new phase2_failing_subjects());
$rolled = false;
try {
    $failing->upsert_from_univer(
        new login_result(880099, $prefix . '_rb', 'STUDENT', 1, null, 'ok'),
        new student_profile(880099, $prefix . '_rb', 'R', 'B', '', null, true, '', '', 0, '', '', ''),
        $sem,
        [$sub1]
    );
} catch (RuntimeException $e) {
    $rolled = true;
}
assert_true($rolled, 'subject failure throws');
assert_true(!$DB->record_exists('user', ['username' => $prefix . '_rb', 'deleted' => 0]), 'rollback removes moodle user');
assert_true(!$DB->record_exists('local_kaznu_univer_user', ['univer_userid' => 880099]), 'rollback removes link');

(new user_service())->purge_for_userid($userid);
(new user_service())->purge_for_userid($cuid);
delete_user($DB->get_record('user', ['id' => $userid], '*', MUST_EXIST));
delete_user($DB->get_record('user', ['id' => $cuid], '*', MUST_EXIST));

echo "\nResult: $passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
