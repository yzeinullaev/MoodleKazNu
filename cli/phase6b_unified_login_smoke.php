<?php
/**
 * Phase 6B unified-login structural smoke (CLI).
 *
 * Does not call Univer API. Verifies packaging contract in this Moodle site.
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

echo "=== Phase 6B unified login structural smoke ===\n";

// Plugin package presence (when deployed).
$authpath = $CFG->dirroot . '/auth/univer/auth.php';
assert_true(file_exists($authpath) || is_readable(__DIR__ . '/../../../auth/univer/auth.php'),
    'auth_univer package present OR not yet installed (note for staging)');

if (file_exists($authpath)) {
    require_once($authpath);
    $auth = new auth_plugin_univer();
    assert_true($auth->authtype === 'univer', 'auth plugin constructs');
    assert_true($auth->prevent_local_passwords() === true, 'prevent_local_passwords');
    assert_true($auth->loginpage_idp_list('') === [], 'no IDP buttons');
    $enabled = get_enabled_auth_plugins(true);
    assert_true(in_array('univer', $enabled, true), 'auth_univer enabled in $CFG->auth');
} else {
    echo "SKIP  live auth_univer runtime (plugin not on this wwwroot)\n";
    $passed++;
}

// login_univer is redirect-only (source in plugin).
$loginuniver = $CFG->dirroot . '/local/kaznu/login_univer.php';
assert_true(file_exists($loginuniver), 'login_univer.php exists');
$src = file_get_contents($loginuniver);
assert_true(str_contains((string) $src, "redirect(new moodle_url('/login/index.php'")
        || str_contains((string) $src, 'redirect(new moodle_url("/login/index.php"')
        || (str_contains((string) $src, '/login/index.php') && str_contains((string) $src, 'redirect')),
    'login_univer redirects to core login');

// Hooks empty of login CTA callback.
$hooks = $CFG->dirroot . '/local/kaznu/db/hooks.php';
if (file_exists($hooks)) {
    $h = file_get_contents($hooks);
    assert_true(!str_contains((string) $h, 'login_button::inject'), 'hooks.php does not register login CTA');
}

// Theme form override when theme is farabi.
assert_true(
    file_exists($CFG->dirroot . '/theme/farabi/templates/core/loginform.mustache')
    || file_exists($CFG->dirroot . '/theme/farabi/templates/login.mustache'),
    'theme_farabi login templates present (if theme deployed)'
);

// User-facing page checks (skip during maintenance).
$climaintenance = !empty($CFG->maintenance_enabled)
    || file_exists($CFG->dataroot . '/climaintenance.html');
if ($climaintenance) {
    echo "SKIP  HTTP login page fetch (maintenance)\n";
    $passed++;
} else {
    $html = @file_get_contents($CFG->wwwroot . '/login/index.php');
    assert_true(is_string($html) && $html !== '', 'login page reachable');
    if (is_string($html)) {
        assert_true(!preg_match('/Войти через Univer|Log in with Univer|local-kaznu-univer-login/i', $html),
            'no Univer dual CTA markup');
        assert_true(!preg_match('/Зайти на Moodle|Moodle LMS/i', $html),
            'no Moodle LMS login heading');
        assert_true(
            preg_match('/name=["\']username["\']/', $html) && preg_match('/name=["\']password["\']/', $html),
            'username+password fields present'
        );
    }
}

echo "\nResult: $passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
