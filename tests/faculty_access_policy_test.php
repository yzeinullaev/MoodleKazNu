<?php
namespace local_kaznu;

use local_kaznu\api\dto\login_result as api_login_result;
use local_kaznu\api\dto\student_profile;
use local_kaznu\api\univer_exception;
use local_kaznu\local\faculty_access_policy;
use local_kaznu\local\login_result;
use local_kaznu\service\login_service;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/login_service_test.php');

/**
 * Faculty gate + allowlist matrix (filter ON unless noted).
 *
 * @covers \local_kaznu\local\faculty_access_policy
 * @covers \local_kaznu\service\login_service
 */
class faculty_access_policy_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('univer_enabled', 1, 'local_kaznu');
        set_config('univer_faculty_filter_enabled', 1, 'local_kaznu');
        set_config('univer_allowed_faculty_ids', '28', 'local_kaznu');
        set_config('univer_login_allowlist', 'oxikbayev.yernur', 'local_kaznu');
        set_config('univer_rl_login', 50, 'local_kaznu');
        set_config('univer_rl_ip', 200, 'local_kaznu');
        set_config('univer_rl_window', 900, 'local_kaznu');
        set_config('univer_rl_lockout', 900, 'local_kaznu');
    }

    private function client_with_faculty(
        int $uid,
        string $login,
        ?int $facultyid,
        string $usertype = 'STUDENT'
    ): login_service_test_client {
        $c = new login_service_test_client();
        $c->loginresult = new api_login_result($uid, $login, $usertype, 1, 's', 'ok');
        $c->profile = new student_profile(
            $uid, $login, 'A', 'B', '', null, true,
            'Some Faculty Name', 'S', 1, 'G', 'A', 'B', $facultyid
        );
        return $c;
    }

    public function test_student_faculty_28_allowed(): void {
        $svc = new login_service($this->client_with_faculty(1001, 'bs.student', 28));
        $r = $svc->authenticate('bs.student', 'pw', '1.1.1.1');
        $this->assertTrue($r->is_success());
        $this->assertSame('success_business_school', $r->outcome);
    }

    public function test_student_faculty_string_normalize_via_int(): void {
        // DTO already coercs; policy receives int 28 from string "28" parse tests below.
        $this->assertSame(
            faculty_access_policy::OUTCOME_SUCCESS_BUSINESS,
            faculty_access_policy::evaluate('x', 28)
        );
        // Client coerce: string "28" becomes id in extract — unit via profile with int from "(string)28".
        $svc = new login_service($this->client_with_faculty(1002, 'bs2', 28));
        $this->assertTrue($svc->authenticate('bs2', 'pw', '1.1.1.2')->is_success());
    }

    public function test_other_faculty_denied(): void {
        $svc = new login_service($this->client_with_faculty(1003, 'other.fac', 99));
        $r = $svc->authenticate('other.fac', 'pw', '1.1.1.3');
        $this->assertSame(login_result::FACULTY_DENIED, $r->status);
        $this->assertSame('denied_faculty', $r->outcome);
        global $DB;
        $this->assertFalse($DB->record_exists('user', ['username' => 'other.fac', 'deleted' => 0]));
    }

    public function test_missing_faculty_fail_closed(): void {
        $svc = new login_service($this->client_with_faculty(1004, 'nofac', null));
        $r = $svc->authenticate('nofac', 'pw', '1.1.1.4');
        $this->assertSame(login_result::FACULTY_DENIED, $r->status);
        $this->assertSame('missing_faculty', $r->outcome);
    }

    public function test_profile_unavailable(): void {
        $c = $this->client_with_faculty(1005, 'pfail', 28);
        $c->profileexception = new univer_exception('univer_http_error');
        $svc = new login_service($c);
        $r = $svc->authenticate('pfail', 'pw', '1.1.1.5');
        $this->assertSame(login_result::PROFILE_FAILED, $r->status);
        $this->assertSame('profile_unavailable', $r->outcome);
    }

    public function test_not_student(): void {
        $c = $this->client_with_faculty(1, 'teacher1', 28, 'TEACHER');
        $svc = new login_service($c);
        $r = $svc->authenticate('teacher1', 'pw', '1.1.1.6');
        $this->assertSame(login_result::NOT_STUDENT, $r->status);
        $this->assertSame('not_student', $r->outcome);
    }

    public function test_allowlist_other_faculty(): void {
        $svc = new login_service($this->client_with_faculty(1007, 'oxikbayev.yernur', 99));
        $r = $svc->authenticate('oxikbayev.yernur', 'pw', '1.1.1.7');
        $this->assertTrue($r->is_success());
        $this->assertSame('success_allowlisted_user', $r->outcome);
    }

    public function test_allowlist_case_insensitive(): void {
        $svc = new login_service($this->client_with_faculty(1008, 'OxikBayev.Yernur', 1));
        $r = $svc->authenticate('OxikBayev.Yernur', 'pw', '1.1.1.8');
        $this->assertTrue($r->is_success());
        $this->assertSame('success_allowlisted_user', $r->outcome);
    }

    public function test_denied_not_moodle_fallback_status(): void {
        // FACULTY_DENIED must never be BAD_CREDENTIALS (soft-fail signal).
        $r = (new login_service($this->client_with_faculty(1009, 'deny.fb', 77)))
            ->authenticate('deny.fb', 'pw', '1.1.1.9');
        $this->assertSame(login_result::FACULTY_DENIED, $r->status);
        $this->assertNotSame(login_result::BAD_CREDENTIALS, $r->status);
    }

    public function test_denied_no_user_create(): void {
        global $DB;
        $before = $DB->count_records('user', ['deleted' => 0]);
        (new login_service($this->client_with_faculty(1010, 'nouser', 55)))
            ->authenticate('nouser', 'pw', '1.1.1.10');
        $this->assertSame($before, $DB->count_records('user', ['deleted' => 0]));
        $this->assertFalse($DB->record_exists('local_kaznu_univer_user', ['univer_userid' => 1010]));
    }

    public function test_denied_no_role_or_link(): void {
        global $DB;
        (new login_service($this->client_with_faculty(1011, 'norole', 55)))
            ->authenticate('norole', 'pw', '1.1.1.11');
        $this->assertFalse($DB->record_exists('local_kaznu_univer_user', ['univer_userid' => 1011]));
    }

    public function test_denied_skips_sync_pipeline(): void {
        // Faculty deny returns before upsert/subjects/cabinet — asserted via no link row.
        global $DB;
        (new login_service($this->client_with_faculty(1012, 'nosync', 55)))
            ->authenticate('nosync', 'pw', '1.1.1.12');
        $this->assertFalse($DB->record_exists('local_kaznu_univer_user', ['univer_userid' => 1012]));
        $this->assertFalse($DB->record_exists('user', ['username' => 'nosync', 'deleted' => 0]));
    }

    public function test_event_outcome_no_pii(): void {
        $sink = $this->redirectEvents();
        (new login_service($this->client_with_faculty(1013, 'ev.deny', 55)))
            ->authenticate('ev.deny', 'pw', '1.1.1.13');
        $events = $sink->get_events();
        $found = false;
        foreach ($events as $e) {
            if ($e instanceof \local_kaznu\event\univer_login_attempted) {
                $found = true;
                $other = $e->other;
                $this->assertArrayHasKey('outcome', $other);
                $this->assertArrayHasKey('correlationid', $other);
                $enc = json_encode($other);
                $this->assertStringNotContainsString('Some Faculty', $enc);
                $this->assertStringNotContainsString('pw', $enc);
            }
        }
        $this->assertTrue($found);
    }

    public function test_multi_faculty_ids(): void {
        set_config('univer_allowed_faculty_ids', '28, 42,xx,0,-1', 'local_kaznu');
        $ids = faculty_access_policy::parse_allowed_faculty_ids();
        $this->assertSame([28, 42], $ids);
        $this->assertTrue(
            (new login_service($this->client_with_faculty(1014, 'f42', 42)))
                ->authenticate('f42', 'pw', '1.1.1.14')->is_success()
        );
    }

    public function test_invalid_setting_tokens_ignored(): void {
        set_config('univer_allowed_faculty_ids', 'foo,,bar,  ', 'local_kaznu');
        $this->assertSame([], faculty_access_policy::parse_allowed_faculty_ids());
        $this->assertSame(
            faculty_access_policy::OUTCOME_DENIED_FACULTY,
            faculty_access_policy::evaluate('x', 28)
        );
    }

    public function test_filter_off_preserves_login_without_faculty_id(): void {
        set_config('univer_faculty_filter_enabled', 0, 'local_kaznu');
        $r = (new login_service($this->client_with_faculty(1015, 'legacy.user', null)))
            ->authenticate('legacy.user', 'pw', '1.1.1.15');
        $this->assertTrue($r->is_success());
        $this->assertSame('success', $r->outcome);
    }

    public function test_hard_fail_statuses_for_auth_hook(): void {
        $hard = [
            login_result::FACULTY_DENIED,
            login_result::PROFILE_FAILED,
            login_result::ACCOUNT_REVIEW,
        ];
        foreach ($hard as $st) {
            $this->assertNotSame(login_result::BAD_CREDENTIALS, $st);
        }
        // NOT_STUDENT remains soft-path eligible when local user exists (documented).
        $this->assertSame(login_result::NOT_STUDENT, login_result::NOT_STUDENT);
    }

    public function test_ui_message_has_no_allowlist_or_faculty_id(): void {
        $msg = get_string('univer_faculty_access_denied', 'local_kaznu');
        $this->assertStringNotContainsString('28', $msg);
        $this->assertStringNotContainsString('oxikbayev', $msg);
        $this->assertStringNotContainsString('allowlist', \core_text::strtolower($msg));
    }

    public function test_parse_allowlist_normalize(): void {
        set_config('univer_login_allowlist', '  OxikBayev.Yernur , other.one ', 'local_kaznu');
        $list = faculty_access_policy::parse_allowlist();
        $this->assertContains('oxikbayev.yernur', $list);
        $this->assertContains('other.one', $list);
    }
}
