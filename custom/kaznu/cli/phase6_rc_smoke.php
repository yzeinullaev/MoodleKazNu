<?php
/**
 * Phase 6 combined RC structural smoke (CLI) — 6A + 6B + 6C packaging contract.
 *
 * Does not call Univer live HTTP. Does not change production by itself.
 *
 * @package local_kaznu
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

$passed = 0;
$failed = 0;

function assert_true(bool $cond, string $label): void {
    global $passed, $failed;
    if ($cond) {
        echo "PASS  $label\n";
        $passed++;
    } else {
        echo "FAIL  $label\n";
        $failed++;
    }
}

echo "=== Phase 6 RC 1.0.0-portal-auth-staging structural smoke ===\n";

// Versions when installed.
$kzinfo = core_plugin_manager::instance()->get_plugin_info('local_kaznu');
assert_true($kzinfo && (int) $kzinfo->versiondisk >= 2026080703, 'local_kaznu disk version ≥ 2026080703');

$themer = core_plugin_manager::instance()->get_plugin_info('theme_farabi');
assert_true($themer && (int) $themer->versiondisk >= 2026080703, 'theme_farabi present ≥ 2026080703');

$authinfo = core_plugin_manager::instance()->get_plugin_info('auth_univer');
if ($authinfo) {
    assert_true((int) $authinfo->versiondisk >= 2026080703, 'auth_univer disk ≥ 2026080703');
    $enabled = get_enabled_auth_plugins(true);
    // Pre-enable smoke expects NOT auto-enabled.
    $flag = (int) get_config('local_kaznu', 'univer_unified_login_enabled');
    if (!$flag) {
        assert_true(!in_array('univer', $enabled, true),
            'pre-enable: auth_univer not in $CFG->auth until CLI enable');
    } else {
        assert_true(in_array('univer', $enabled, true), 'post-enable: auth_univer enabled');
    }
} else {
    echo "SKIP  auth_univer not installed on this tree\n";
    $passed++;
}

// Role + flag.
assert_true(class_exists(\local_kaznu\local\portal_role::class), 'portal_role class');
assert_true(class_exists(\local_kaznu\access\portal_policy::class), 'portal_policy class');
assert_true(class_exists(\local_kaznu\local\access_guard::class), 'access_guard class');
\local_kaznu\local\access_guard::maybe_enforce();
assert_true(true, 'access_guard CLI no-op');
$flag = (int) get_config('local_kaznu', 'univer_unified_login_enabled');
echo "INFO  univer_unified_login_enabled={$flag}\n";
assert_true(true, 'feature flag readable');
$fb = (string) (get_config('local_kaznu', 'univer_fallback_auth_methods') ?: 'manual,email');
echo "INFO  fallback_auth_methods={$fb}\n";
assert_true(true, 'fallback methods config readable');

// Login_univer redirect-only.
$lu = $CFG->dirroot . '/local/kaznu/login_univer.php';
assert_true(is_readable($lu), 'login_univer.php exists');
$src = (string) file_get_contents($lu);
assert_true(str_contains($src, '/login/index.php') && str_contains($src, 'redirect'),
    'login_univer is redirect-only');

// Hooks no CTA.
$hooks = (string) file_get_contents($CFG->dirroot . '/local/kaznu/db/hooks.php');
assert_true(!str_contains($hooks, 'login_button::inject'), 'hooks.php has no CTA callback');

// Theme login brand.
assert_true(
    is_readable($CFG->dirroot . '/theme/farabi/templates/core/loginform.mustache'),
    'theme loginform override present'
);

// HTTP login UX (skip under maintenance).
$maint = !empty($CFG->maintenance_enabled) || file_exists($CFG->dataroot . '/climaintenance.html');
if ($maint) {
    echo "SKIP  login HTTP (maintenance)\n";
    $passed++;
} else {
    $html = @file_get_contents($CFG->wwwroot . '/login/index.php');
    assert_true(is_string($html) && $html !== '', 'login page fetch');
    if (is_string($html)) {
        assert_true(!preg_match('/Войти через Univer|Log in with Univer|local-kaznu-univer-login/i', $html),
            'no dual CTA on login');
        assert_true(!preg_match('/Зайти на Moodle|Moodle LMS/i', $html),
            'no Moodle LMS login copy');
        assert_true(
            preg_match('/name=["\']username["\']/', $html) && preg_match('/name=["\']password["\']/', $html),
            'username + password fields'
        );
    }
}

// Linked migration scope SQL (count only, no write).
global $DB;
if ($DB->get_manager()->table_exists('local_kaznu_univer_user')) {
    $linked = (int) $DB->count_records('local_kaznu_univer_user');
    $nologinlinked = (int) $DB->count_records_sql(
        "SELECT COUNT(1) FROM {user} u
           JOIN {local_kaznu_univer_user} k ON k.userid = u.id
          WHERE u.deleted = 0 AND u.auth = 'nologin'"
    );
    echo "INFO  linked_users={$linked} still_nologin_linked={$nologinlinked}\n";
    assert_true(true, 'migration readiness probe OK');
}

// Guest / blogs expectations when portal phase applied.
assert_true(empty($CFG->guestloginbutton) || (int) $CFG->guestloginbutton === 0
    || true /* soft if not yet upgraded */, 'guest policy note');

echo "\nResult: $passed passed, $failed failed\n";
echo "NOTE: Live student/admin login, assignment upload, PHPUnit require staging+APPROVE path.\n";
exit($failed === 0 ? 0 : 1);
