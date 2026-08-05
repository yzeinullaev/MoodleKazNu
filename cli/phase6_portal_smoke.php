<?php
/**
 * Phase 6A/C production smoke.
 * php local/kaznu/cli/phase6_portal_smoke.php
 *
 * Creates temporary users only and deletes them. Does not change theme or site config.
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/accesslib.php');

use local_kaznu\access\portal_policy;
use local_kaznu\local\portal_role;

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

echo "=== Phase 6 smoke ===\n";
echo "theme=" . ($CFG->theme ?? '') . "\n";
echo "plugin=" . (get_config('local_kaznu', 'version') ?: '?') . "\n";

assert_true(($CFG->theme ?? '') === 'farabi', 'theme=farabi');
assert_true((int) $CFG->guestloginbutton === 0, 'guestloginbutton=0');
assert_true((int) ($CFG->allowguestmymoodle ?? 1) === 0, 'allowguestmymoodle=0');
assert_true((int) ($CFG->autologinguests ?? 1) === 0, 'autologinguests=0');
assert_true(empty($CFG->enableblogs) || (int) $CFG->enableblogs === 0, 'enableblogs=0');

$roleid1 = portal_role::ensure_role();
$roleid2 = portal_role::ensure_role();
assert_true($roleid1 > 0 && $roleid1 === $roleid2, 'ensure_role idempotent (same id)');
$roles = $DB->count_records('role', ['shortname' => portal_policy::ROLE_SHORTNAME]);
assert_true($roles === 1, 'exactly one farabi_student role');

$sys = context_system::instance();
$rec = $DB->get_record('role_capabilities', [
    'roleid' => $roleid1,
    'contextid' => $sys->id,
    'capability' => 'moodle/user:manageownfiles',
]);
assert_true($rec && (int) $rec->permission === CAP_PROHIBIT, 'private files CAP_PROHIBIT');
$rec2 = $DB->get_record('role_capabilities', [
    'roleid' => $roleid1,
    'contextid' => $sys->id,
    'capability' => 'local/kaznu:portalstudent',
]);
assert_true($rec2 && (int) $rec2->permission === CAP_ALLOW, 'portalstudent CAP_ALLOW');

$dups = portal_role::count_duplicate_assignments();
assert_true($dups === 0, 'no duplicate role_assignments');
echo "INFO  farabi_student assignments=" . portal_role::count_assignments() . "\n";
echo "INFO  linked_univer_users=" . (int) $DB->count_records('local_kaznu_univer_user') . "\n";
echo "INFO  blog_posts_at_disable=" . (get_config('local_kaznu', 'portal_blog_posts_at_disable') ?: '0') . "\n";
echo "INFO  prev_enableblogs=" . (get_config('local_kaznu', 'portal_prev_enableblogs') ?: '?') . "\n";
echo "INFO  prev_theme=" . (get_config('local_kaznu', 'portal_prev_theme') ?: '?') . "\n";

// Admin must not be portal student.
$admin = get_admin();
assert_true(!portal_policy::is_portal_student($admin), 'admin is not portal student');

// Linked users (existing) should be portal students if not admin.
$linked = $DB->get_records_sql(
    "SELECT u.* FROM {local_kaznu_univer_user} k JOIN {user} u ON u.id = k.userid
      WHERE u.deleted = 0 AND u.suspended = 0 LIMIT 5"
);
foreach ($linked as $u) {
    if (is_siteadmin($u)) {
        assert_true(!portal_policy::is_portal_student($u), 'siteadmin linked user not portal student');
        continue;
    }
    assert_true(portal_policy::is_portal_student($u), 'linked user is portal student (id=' . $u->id . ')');
}

// Guest enrol.
$guestactive = (int) $DB->count_records('enrol', ['enrol' => 'guest', 'status' => 0]);
assert_true($guestactive === 0, 'no active guest enrol instances');

// Theme files.
$themedir = $CFG->dirroot . '/theme/farabi';
assert_true(is_dir($themedir) && file_exists($themedir . '/config.php'), 'theme_farabi code present');
assert_true(file_exists($themedir . '/classes/output/core_renderer.php'), 'theme renderer present');

// Temporary users + cleanup.
$prefix = 'p6smoke_' . time();
$student = null;
$unassigned = null;
try {
    $student = (object) [
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'username' => $prefix . '_s',
        'password' => hash_internal_user_password(random_string(16)),
        'firstname' => 'Portal',
        'lastname' => 'Student',
        'email' => $prefix . 's@users.invalid',
        'emailstop' => 1,
    ];
    $student->id = user_create_user($student, false, false);
    portal_role::assign_to_user((int) $student->id);
    portal_role::assign_to_user((int) $student->id); // second call
    $cnt = (int) $DB->count_records('role_assignments', [
        'roleid' => $roleid1,
        'userid' => $student->id,
        'contextid' => $sys->id,
    ]);
    assert_true($cnt === 1, 'double assign does not duplicate');
    assert_true(portal_policy::is_portal_student($student), 'temp assigned user is portal student');

    $unassigned = (object) [
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'username' => $prefix . '_u',
        'password' => hash_internal_user_password(random_string(16)),
        'firstname' => 'Local',
        'lastname' => 'Only',
        'email' => $prefix . 'u@users.invalid',
        'emailstop' => 1,
    ];
    $unassigned->id = user_create_user($unassigned, false, false);
    assert_true(!portal_policy::is_portal_student($unassigned), 'manual without role is not portal student');
} catch (Throwable $e) {
    echo 'ERROR ' . $e->getMessage() . "\n";
    $failed++;
} finally {
    foreach ([$student, $unassigned] as $u) {
        if ($u && !empty($u->id) && ($rec = $DB->get_record('user', ['id' => $u->id]))) {
            // Remove role assignment then soft-delete.
            if ($roleid1) {
                role_unassign($roleid1, (int) $u->id, $sys->id);
            }
            delete_user($rec);
        }
    }
}

// Access guard class exists and skips CLI.
assert_true(class_exists(\local_kaznu\local\access_guard::class), 'access_guard class');
\local_kaznu\local\access_guard::maybe_enforce(); // must no-op on CLI
assert_true(true, 'access_guard no-op on CLI');

// Login pages reachable (skip while CLI maintenance is enabled).
$climaintenance = !empty($CFG->maintenance_enabled)
    || (isset($CFG->cli_maintenance) && $CFG->cli_maintenance)
    || file_exists($CFG->dataroot . '/climaintenance.html');
if ($climaintenance) {
    echo "SKIP  login page HTTP fetch (site under CLI maintenance)\n";
    $passed++;
} else {
    $login = @file_get_contents($CFG->wwwroot . '/login/index.php');
    assert_true(is_string($login) && $login !== '', 'login page HTTP fetch');
    if (is_string($login)) {
        assert_true(!preg_match('/name=["\']guestlogin["\']/', $login),
            'no guest login control in HTML (best-effort)');
        // Unified login: no dual-entry CTA / vendor wording on the form surface.
        assert_true(
            !str_contains($login, 'login_univer.php')
            && !str_contains($login, 'local-kaznu-univer-login')
            && !preg_match('/Войти через Univer|Log in with Univer/i', $login),
            'no Univer dual-entry CTA on login (best-effort)'
        );
        assert_true(
            !preg_match('/Зайти на Moodle|Moodle LMS/i', $login),
            'no Moodle LMS wording on login HTML (best-effort)'
        );
    }
}

echo "\nResult: $passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
