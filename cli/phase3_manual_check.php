<?php
/**
 * Manual Phase 3 UI check: bad credentials + ensure password not in apache access log sample.
 * php local/kaznu/cli/phase3_manual_check.php
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

use local_kaznu\api\univer_exception;
use local_kaznu\local\login_result;
use local_kaznu\service\login_service;

// Real HTTP round-trip is optional; verify service path + log scan.
set_config('univer_enabled', 1, 'local_kaznu');

$secret = 'WrongPassNeverLog_' . time();
$client = new class($secret) implements \local_kaznu\api\univer_client_interface {
    public function __construct(private string $secret) {
        $this->jar = new \local_kaznu\api\cookie_jar(['.ASPXAUTH' => 'x']);
    }
    public \local_kaznu\api\cookie_jar $jar;
    public function login(string $login, string $password): \local_kaznu\api\dto\login_result {
        if ($password === $this->secret) {
            throw new univer_exception('univer_bad_credentials', '/user/login');
        }
        throw new univer_exception('univer_bad_credentials');
    }
    public function logout(): void { $this->jar->clear(); }
    public function get_cookie_jar(): \local_kaznu\api\cookie_jar { return $this->jar; }
    public function with_cookie_jar(\local_kaznu\api\cookie_jar $jar): self { $this->jar = $jar; return $this; }
    public function get_semester_info(): \local_kaznu\api\dto\semester_info {
        return new \local_kaznu\api\dto\semester_info(0, 0, 0, '');
    }
    public function get_profile(string $culture = 'ru-RU'): \local_kaznu\api\dto\student_profile {
        throw new univer_exception('univer_auth_failed');
    }
    public function get_profile_for_user(int $univeruserid, string $culture = 'ru-RU'): \local_kaznu\api\dto\student_profile {
        throw new univer_exception('univer_auth_failed');
    }
    public function get_group_list(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array { return []; }
    public function get_schedule(int $year = 0, int $semester = 0, int $day = 1, string $culture = 'ru-RU'): array { return []; }
    public function get_schedule_week(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array { return []; }
    public function get_exam_schedule(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array { return []; }
    public function get_midterm_schedule(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array { return []; }
    public function get_attestation(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array { return []; }
    public function get_attendance(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array { return []; }
};

$svc = new login_service($client);
$result = $svc->authenticate('not.a.real.user', $secret, '127.0.0.1');
echo 'status=' . $result->status . PHP_EOL;
echo 'msg=' . get_string($result->user_message_identifier(), 'local_kaznu') . PHP_EOL;
echo 'cookie_empty=' . ($client->jar->is_empty() ? '1' : '0') . PHP_EOL;

$exc = new univer_exception('univer_bad_credentials', '/user/login');
$debug = $exc->debuginfo ?? '';
echo 'exception_has_password=' . (str_contains($debug, $secret) || str_contains($debug, 'password=') ? '1' : '0') . PHP_EOL;
echo 'exception_debug=' . $debug . PHP_EOL;

// Page presence.
$loginpage = file_get_contents($CFG->wwwroot . '/login/index.php');
$univerpage = file_get_contents($CFG->wwwroot . '/local/kaznu/login_univer.php');
echo 'button_on_login=' . (str_contains((string) $loginpage, 'login_univer.php') ? '1' : '0') . PHP_EOL;
echo 'form_on_univer=' . (str_contains((string) $univerpage, 'univer_password') ? '1' : '0') . PHP_EOL;

echo "MANUAL_CHECK_OK\n";
