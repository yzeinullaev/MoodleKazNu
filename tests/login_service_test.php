<?php
namespace local_kaznu;

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

defined('MOODLE_INTERNAL') || die();

/**
 * Configurable Univer client stub for login_service tests.
 */
class login_service_test_client implements univer_client_interface {
    public cookie_jar $jar;
    public ?api_login_result $loginresult = null;
    public ?univer_exception $loginexception = null;
    public ?student_profile $profile = null;
    public ?univer_exception $profileexception = null;
    public ?semester_info $semester = null;
    public bool $logoutcalled = false;
    public array $logincalls = [];

    public function __construct() {
        $this->jar = new cookie_jar(['.ASPXAUTH' => 'tok', 'ASP.NET_SessionId' => 'sid']);
    }

    public function login(string $login, string $password): api_login_result {
        $this->logincalls[] = ['login' => $login, 'password_len' => strlen($password)];
        if ($this->loginexception) {
            throw $this->loginexception;
        }
        if (!$this->loginresult) {
            throw new univer_exception('univer_auth_failed');
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
        return $this->semester ?? new semester_info(2025, 1, 8, 'RK1');
    }

    public function get_profile(string $culture = 'ru-RU'): student_profile {
        return $this->get_profile_for_user($this->loginresult?->univeruserid ?? 0, $culture);
    }

    public function get_profile_for_user(int $univeruserid, string $culture = 'ru-RU'): student_profile {
        if ($this->profileexception) {
            throw $this->profileexception;
        }
        if ($this->profile) {
            return $this->profile;
        }
        return new student_profile(
            $univeruserid, 'student.login', 'A', 'B', '', null, true,
            'F', 'S', 1, 'G', 'A', 'B'
        );
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

/**
 * @covers \local_kaznu\service\login_service
 * @covers \local_kaznu\local\return_url
 * @covers \local_kaznu\local\login_rate_limiter
 */
class login_service_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('univer_enabled', 1, 'local_kaznu');
        set_config('univer_rl_login', 3, 'local_kaznu');
        set_config('univer_rl_ip', 20, 'local_kaznu');
        set_config('univer_rl_window', 900, 'local_kaznu');
        set_config('univer_rl_lockout', 900, 'local_kaznu');
    }

    private function ok_client(int $uid = 297051, string $login = 'oxikbayev.yernur'): login_service_test_client {
        $c = new login_service_test_client();
        $c->loginresult = new api_login_result($uid, $login, 'STUDENT', 1, 's', 'ok');
        $c->profile = new student_profile(
            $uid, $login, 'Yernur', 'Oxikbayev', '', null, true,
            'Fac', 'Spec', 2, 'G1', 'Yernur', 'Oxikbayev'
        );
        return $c;
    }

    public function test_create_new_user_login(): void {
        $client = $this->ok_client();
        $svc = new login_service($client);
        $result = $svc->authenticate('oxikbayev.yernur', 'Secret', '10.0.0.1');

        $this->assertTrue($result->is_success());
        $this->assertTrue($result->created);
        $this->assertContains($result->user->auth, ['univer', 'nologin']);
        $this->assertSame('univer_297051@users.invalid', $result->user->email);
        $this->assertTrue($client->logoutcalled);
        $this->assertTrue($client->jar->is_empty());
    }

    public function test_existing_linked_user_no_duplicate(): void {
        $client = $this->ok_client();
        $svc = new login_service($client);
        $first = $svc->authenticate('oxikbayev.yernur', 'Secret', '10.0.0.1');
        $second = $svc->authenticate('oxikbayev.yernur', 'Secret', '10.0.0.1');

        $this->assertTrue($second->is_success());
        $this->assertFalse($second->created);
        $this->assertEquals($first->user->id, $second->user->id);

        global $DB;
        $this->assertEquals(1, $DB->count_records('local_kaznu_univer_user', ['univer_userid' => 297051]));
    }

    public function test_bad_credentials(): void {
        $client = new login_service_test_client();
        $client->loginexception = new univer_exception('univer_bad_credentials');
        $svc = new login_service($client);
        $result = $svc->authenticate('u', 'wrong', '10.0.0.2');
        $this->assertSame(login_result::BAD_CREDENTIALS, $result->status);
        $this->assertTrue($client->jar->is_empty());
    }

    public function test_api_unavailable(): void {
        $client = new login_service_test_client();
        $client->loginexception = new univer_exception('univer_http_error');
        $svc = new login_service($client);
        $result = $svc->authenticate('u', 'p', '10.0.0.3');
        $this->assertSame(login_result::UNAVAILABLE, $result->status);
    }

    public function test_not_student(): void {
        $client = $this->ok_client();
        $client->loginresult = new api_login_result(1, 'teacher1', 'TEACHER', 1, null, 'ok');
        $svc = new login_service($client);
        $result = $svc->authenticate('teacher1', 'p', '10.0.0.4');
        $this->assertSame(login_result::NOT_STUDENT, $result->status);
    }

    public function test_profile_missing_userid(): void {
        $client = $this->ok_client(0, 'bad.user');
        $client->loginresult = new api_login_result(0, 'bad.user', 'STUDENT', 1, null, 'ok');
        $svc = new login_service($client);
        $result = $svc->authenticate('bad.user', 'p', '10.0.0.5');
        $this->assertSame(login_result::PROFILE_FAILED, $result->status);
    }

    public function test_username_conflict(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');

        $existing = $this->getDataGenerator()->create_user(['username' => 'taken.login']);
        $DB->insert_record('local_kaznu_univer_user', (object) [
            'userid' => $existing->id,
            'univer_userid' => 111,
            'univer_login' => 'taken.login',
            'faculty' => '', 'speciality' => '', 'course_number' => 0, 'group_name' => '',
            'current_year' => 0, 'current_semester' => 0, 'email_is_technical' => 0,
            'status' => univer_status::ACTIVE, 'lastsync' => time(),
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $client = $this->ok_client(999, 'taken.login');
        $svc = new login_service($client);
        $result = $svc->authenticate('taken.login', 'p', '10.0.0.6');
        $this->assertSame(login_result::ACCOUNT_REVIEW, $result->status);
        $this->assertStringNotContainsString('taken.login', get_string($result->user_message_identifier(), 'local_kaznu'));
    }

    public function test_disabled_integration(): void {
        set_config('univer_enabled', 0, 'local_kaznu');
        $svc = new login_service($this->ok_client());
        $result = $svc->authenticate('u', 'p', '10.0.0.7');
        $this->assertSame(login_result::DISABLED, $result->status);
    }

    public function test_rate_limit_and_reset(): void {
        $client = new login_service_test_client();
        $client->loginexception = new univer_exception('univer_bad_credentials');
        $limiter = new login_rate_limiter(3, 50, 900, 900);
        $svc = new login_service($client, null, $limiter);

        $this->assertSame(login_result::BAD_CREDENTIALS, $svc->authenticate('rl.user', 'x', '10.0.0.8')->status);
        $this->assertSame(login_result::BAD_CREDENTIALS, $svc->authenticate('rl.user', 'x', '10.0.0.8')->status);
        $this->assertSame(login_result::BAD_CREDENTIALS, $svc->authenticate('rl.user', 'x', '10.0.0.8')->status);
        $blocked = $svc->authenticate('rl.user', 'x', '10.0.0.8');
        $this->assertSame(login_result::RATE_LIMITED, $blocked->status);

        // Success resets login counter.
        $ok = $this->ok_client(555, 'rl.user');
        $limiter2 = new login_rate_limiter(3, 50, 900, 900);
        // Fresh limiter state is empty — simulate prior failures then success.
        $limiter2->register_failure('rl.ok', '10.0.0.9');
        $limiter2->register_failure('rl.ok', '10.0.0.9');
        $svcok = new login_service($ok, null, $limiter2);
        $res = $svcok->authenticate('rl.ok', 'good', '10.0.0.9');
        $this->assertTrue($res->is_success());
        $this->assertFalse($limiter2->is_blocked('rl.ok', '10.0.0.9'));
    }

    public function test_return_url_local_and_external(): void {
        global $CFG;
        $local = return_url::sanitize('/course/view.php?id=2');
        $this->assertStringContainsString('/course/view.php', $local->out(false));

        $abs = return_url::sanitize($CFG->wwwroot . '/my/');
        $this->assertTrue(return_url::is_local($CFG->wwwroot . '/my/'));

        $ext = return_url::sanitize('https://evil.example/phish');
        $this->assertFalse(return_url::is_local('https://evil.example/phish'));
        $this->assertStringContainsString('/my/', $ext->out(false));

        $this->assertFalse(return_url::is_local('javascript:alert(1)'));
        $this->assertFalse(return_url::is_local('//evil.example/x'));
    }

    public function test_password_not_in_exception_debug(): void {
        $client = new login_service_test_client();
        $secret = 'SuperSecretPassword99';
        $client->loginexception = new univer_exception('univer_bad_credentials', '/user/login');
        $svc = new login_service($client);
        $result = $svc->authenticate('u', $secret, '10.0.0.10');
        $this->assertSame(login_result::BAD_CREDENTIALS, $result->status);
        $this->assertStringNotContainsString($secret, $result->correlationid);
        $this->assertStringNotContainsString('password=', json_encode($client->logincalls));
        // Stub records length only.
        $this->assertSame(strlen($secret), $client->logincalls[0]['password_len']);
    }

    public function test_user_service_failure_no_login_user(): void {
        $client = $this->ok_client(4242, 'fail.sync');
        $users = new class extends \local_kaznu\service\user_service {
            public function upsert_from_univer(
                \local_kaznu\api\dto\login_result $login,
                \local_kaznu\api\dto\student_profile $profile,
                ?\local_kaznu\api\dto\semester_info $semester = null,
                array $subjectlist = []
            ): array {
                throw new \RuntimeException('boom');
            }
        };
        $svc = new login_service($client, $users);
        $result = $svc->authenticate('fail.sync', 'p', '10.0.0.11');
        $this->assertSame(login_result::SYNC_FAILED, $result->status);
        global $DB;
        $this->assertFalse($DB->record_exists('user', ['username' => 'fail.sync', 'deleted' => 0]));
    }
}
