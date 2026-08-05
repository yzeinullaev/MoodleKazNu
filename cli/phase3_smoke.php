<?php
/**
 * Phase 3 smoke (no production accounts). Uses stub Univer client.
 *
 * php local/kaznu/cli/phase3_smoke.php
 */
define('CLI_SCRIPT', true);

// Allow stub login smoke on HTTP-only hosts (never for real passwords).
putenv('LOCAL_KAZNU_ALLOW_HTTP_LOGIN=1');

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/user/lib.php');

use local_kaznu\api\cookie_jar;
use local_kaznu\api\dto\login_result as api_login_result;
use local_kaznu\api\dto\semester_info;
use local_kaznu\api\dto\student_profile;
use local_kaznu\api\univer_client_interface;
use local_kaznu\api\univer_exception;
use local_kaznu\local\login_rate_limiter;
use local_kaznu\local\login_result;
use local_kaznu\local\return_url;
use local_kaznu\service\login_service;
use local_kaznu\univer_status;

class phase3_stub_client implements univer_client_interface {
    public cookie_jar $jar;
    public $loginresult;
    public $loginexception;
    public $profile;
    public bool $logoutcalled = false;

    public function __construct() {
        $this->jar = new cookie_jar(['.ASPXAUTH' => 'x', 'ASP.NET_SessionId' => 'y']);
    }

    public function login(string $login, string $password): api_login_result {
        if ($this->loginexception) {
            throw $this->loginexception;
        }
        return $this->loginresult;
    }
    public function logout(): void {
        $this->logoutcalled = true;
        $this->jar->clear();
    }
    public function get_cookie_jar(): cookie_jar {
        return $this->jar;
    }
    public function with_cookie_jar(cookie_jar $jar): self {
        $this->jar = $jar;
        return $this;
    }
    public function get_semester_info(): semester_info {
        return new semester_info(2025, 1, 8, '');
    }
    public function get_profile(string $culture = 'ru-RU'): student_profile {
        return $this->profile;
    }
    public function get_profile_for_user(int $univeruserid, string $culture = 'ru-RU'): student_profile {
        return $this->profile;
    }
    public function get_group_list(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array {
        return [];
    }
    public function get_schedule(int $year = 0, int $semester = 0, int $day = 1, string $culture = 'ru-RU'): array {
        return [];
    }
    public function get_schedule_week(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array {
        return [];
    }
    public function get_exam_schedule(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array {
        return [];
    }
    public function get_midterm_schedule(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array {
        return [];
    }
    public function get_attestation(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array {
        return [];
    }
    public function get_attendance(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array {
        return [];
    }
}

$passed = 0;
$failed = 0;
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

set_config('univer_enabled', 1, 'local_kaznu');
$prefix = 'p3smoke_' . time();
$loginname = $prefix . '_u';

$client = new phase3_stub_client();
$client->loginresult = new api_login_result(880301, $loginname, 'STUDENT', 1, null, 'ok');
$client->profile = new student_profile(
    880301, $loginname, 'P3', 'Smoke', '', null, true, 'F', 'S', 1, 'G', 'P3', 'Smoke'
);

$svc = new login_service($client, null, new login_rate_limiter(5, 50, 900, 900));
$r1 = $svc->authenticate($loginname, 'SecretPass', '127.0.0.1');
assert_true($r1->is_success() && $r1->created, 'create + authenticate');
assert_true(in_array($r1->user->auth, ['nologin', 'univer'], true), 'auth=univer or legacy nologin');
assert_true($client->logoutcalled && $client->jar->is_empty(), 'cookie jar cleared');

$r2 = $svc->authenticate($loginname, 'SecretPass', '127.0.0.1');
assert_true($r2->is_success() && !$r2->created, 'repeat without duplicate');

$bad = new phase3_stub_client();
$bad->loginexception = new univer_exception('univer_bad_credentials');
$rb = (new login_service($bad))->authenticate($prefix . '_bad', 'x', '127.0.0.1');
assert_true($rb->status === login_result::BAD_CREDENTIALS, 'bad credentials');

$down = new phase3_stub_client();
$down->loginexception = new univer_exception('univer_server_error');
$rd = (new login_service($down))->authenticate($prefix . '_down', 'x', '127.0.0.1');
assert_true($rd->status === login_result::UNAVAILABLE, 'api unavailable');

set_config('univer_enabled', 0, 'local_kaznu');
$roff = (new login_service($client))->authenticate($loginname, 'x', '127.0.0.1');
assert_true($roff->status === login_result::DISABLED, 'integration disabled');
set_config('univer_enabled', 1, 'local_kaznu');

assert_true(return_url::is_local('/my/'), 'local return url');
assert_true(!return_url::is_local('https://evil.example/'), 'reject external return url');
assert_true(str_contains(return_url::sanitize('https://evil.example/')->out(false), '/my'), 'external → dashboard');

// Rate limit
$limiter = new login_rate_limiter(2, 50, 900, 900);
$failclient = new phase3_stub_client();
$failclient->loginexception = new univer_exception('univer_bad_credentials');
$rlsvc = new login_service($failclient, null, $limiter);
$rlsvc->authenticate($prefix . '_rl', 'a', '10.9.9.9');
$rlsvc->authenticate($prefix . '_rl', 'a', '10.9.9.9');
$rl = $rlsvc->authenticate($prefix . '_rl', 'a', '10.9.9.9');
assert_true($rl->status === login_result::RATE_LIMITED, 'rate limited');

// Standard Moodle login unaffected: demo-style manual user can still exist.
$manual = $DB->get_record('user', ['username' => 'demo_student', 'deleted' => 0]);
assert_true($manual && $manual->auth !== 'nologin', 'standard moodle user still non-nologin');

// Cleanup
if ($r1->user) {
    (new \local_kaznu\service\user_service())->purge_for_userid((int) $r1->user->id);
    delete_user($DB->get_record('user', ['id' => $r1->user->id], '*', MUST_EXIST));
}

// Restore Univer disabled after smoke (HTTP public host).
set_config('univer_enabled', 0, 'local_kaznu');

echo "\nResult: $passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
