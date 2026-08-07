<?php
namespace local_proctopus;

use local_proctopus\callback\stub_hmac_verifier;
use local_proctopus\http\stub_transport;
use local_proctopus\jwt\hs_token_service;
use local_proctopus\jwt\legacy_payload_builder;
use local_proctopus\local\state_token;
use local_proctopus\service\callback_handler;
use local_proctopus\service\session_service;

defined('MOODLE_INTERNAL') || die();

/**
 * Offline P2 tests (no real Proctopus HTTP).
 *
 * @covers \local_proctopus\jwt\legacy_payload_builder
 * @covers \local_proctopus\api\legacy_proctopus_client
 * @covers \local_proctopus\service\session_service
 */
class p2_legacy_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('proctopus_enabled', 0, 'local_proctopus');
        set_config('proctopus_legacy_mode', 1, 'local_proctopus');
        set_config('proctopus_live_calls_enabled', 0, 'local_proctopus');
        set_config('proctopus_account_name', 'farabi_business', 'local_proctopus');
        set_config('proctopus_baseurl', 'https://farabi-proctoring.kaznu.kz', 'local_proctopus');
        set_config('proctopus_start_path', '/integration/simple/farabi_business/start/', 'local_proctopus');
        set_config('proctopus_jwt_algorithm', 'HS256', 'local_proctopus');
        putenv('PROCTOPUS_JWT_SECRET=phpunit-fixture-secret-not-for-prod');
    }

    protected function tearDown(): void {
        putenv('PROCTOPUS_JWT_SECRET');
        parent::tearDown();
    }

    public function test_disabled_mode(): void {
        $r = callback_handler::handle('{}', 'application/json', 'c1');
        $this->assertSame(503, $r['http']);
    }

    public function test_live_calls_flag_off_by_default(): void {
        $this->assertFalse(local_proctopus_live_calls_enabled());
        set_config('proctopus_enabled', 1, 'local_proctopus');
        $this->assertFalse(local_proctopus_live_calls_enabled());
    }

    public function test_jwt_account_name_and_algorithm(): void {
        $svc = new hs_token_service();
        $claims = $svc->build_claims('s1', 'farabi_business', 120, ['iss' => 'farabischool.kz']);
        $this->assertSame('farabi_business', $claims['accountName']);
        $this->assertArrayHasKey('iat', $claims);
        $this->assertArrayHasKey('nbf', $claims);
        $this->assertArrayHasKey('exp', $claims);
        $this->assertArrayHasKey('jti', $claims);
        $token = $svc->encode($claims, 'phpunit-fixture-secret-not-for-prod', 'HS256');
        $hdr = json_decode(base64_decode(strtr(explode('.', $token)[0], '-_', '+/')), true);
        $this->assertSame('HS256', $hdr['alg']);
        $this->assertStringNotContainsString('phpunit-fixture-secret', $token);
    }

    public function test_payload_no_legacy_domains_or_username_third(): void {
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'A', 'lastname' => 'B', 'username' => 'secret.login', 'middlename' => '',
        ]);
        $course = $this->getDataGenerator()->create_course();
        $quizgen = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgen->create_instance(['course' => $course->id, 'timeopen' => 1700000000, 'timeclose' => 1700003600, 'timelimit' => 1800, 'name' => 'Exam <b>X</b>']);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
        $quizrow = $quiz; // stdClass from create has id.

        $builder = new legacy_payload_builder();
        $payload = $builder->build($user, (object)(array)$quizrow, $cm, 'ext1', 'state.token', null);
        $this->assertSame('farabi_business', $payload['accountName']);
        $this->assertSame('', $payload['thirdName']);
        $this->assertSame((int) $user->id, $payload['userId']);
        $this->assertSame(30, $payload['duration']); // 1800s → 30 min
        $this->assertStringNotContainsString('dl.kaznu', $payload['sessionUrl']);
        $this->assertStringNotContainsString('dl.kaznu', $payload['redirectUrl']);
        $this->assertStringNotContainsString('kaznu_moodle', json_encode($payload));
        $this->assertStringNotContainsString('secret.login', $payload['thirdName']);
        // Code.php payload has no registered JWT time/issuer claims.
        $this->assertArrayNotHasKey('iat', $payload);
        $this->assertArrayNotHasKey('nbf', $payload);
        $this->assertArrayNotHasKey('exp', $payload);
        $this->assertArrayNotHasKey('jti', $payload);
        $this->assertArrayNotHasKey('iss', $payload);
        $this->assertArrayHasKey('userId', $payload);
        $this->assertArrayHasKey('sessionUrl', $payload);
        $this->assertArrayHasKey('redirectUrl', $payload);
    }

    public function test_stub_launch_and_return_and_idempotency(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_proctopus_session')) {
            $this->markTestSkipped('tables not installed');
        }
        set_config('proctopus_enabled', 1, 'local_proctopus');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $course->id,
            'name' => 'Proctored quiz',
        ]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);

        $stub = new stub_transport();
        $svc = new session_service(null, $stub);
        $out = $svc->launch_for_cm((int) $user->id, (int) $cm->id);
        $this->assertArrayHasKey('redirect_url', $out);
        $this->assertStringContainsString('/local/proctopus/return.php', $out['redirect_url']);
        $this->assertStringNotContainsString('dl.kaznu', $out['redirect_url']);

        // Parse state from redirect.
        $q = parse_url($out['redirect_url'], PHP_URL_QUERY);
        parse_str((string) $q, $params);
        $this->assertNotEmpty($params['state']);

        $info = $svc->complete_return((int) $user->id, $params['state']);
        $this->assertSame((int) $cm->id, $info['cmid']);
        $this->assertTrue($svc->user_has_admitted_session((int) $user->id, (int) $cm->id));

        // Wrong user IDOR.
        $other = $this->getDataGenerator()->create_user();
        $this->expectException(\moodle_exception::class);
        $svc->complete_return((int) $other->id, $params['state']);
    }

    public function test_expired_return_rejected(): void {
        $tok = state_token::issue([
            'sid' => 1, 'uid' => 2, 'cmid' => 3, 'ext' => 'x', 'exp' => time() - 3600,
        ]);
        $this->assertNull(state_token::verify($tok));
    }

    public function test_callback_invalid_and_stub_valid_and_replay(): void {
        global $DB;
        set_config('proctopus_enabled', 1, 'local_proctopus');
        $verifier = new stub_hmac_verifier();

        $bad = callback_handler::handle('{"eventId":"e1"}', 'application/json', 'c', $verifier);
        $this->assertFalse($bad['body']['ok']);

        $secret = 'phpunit-fixture-secret-not-for-prod';
        $event = 'ev_' . bin2hex(random_bytes(4));
        $session = 'fps_test';
        $status = 'done';
        $ts = time();
        $sig = hash_hmac('sha256', $event . '|' . $session . '|' . $status . '|' . $ts, $secret);
        $body = json_encode([
            'eventId' => $event,
            'sessionId' => $session,
            'status' => $status,
            'ts' => $ts,
            'sig' => $sig,
        ]);
        $ok = callback_handler::handle($body, 'application/json', 'c2', $verifier);
        $this->assertTrue($ok['body']['ok'] ?? false);

        if ($DB->get_manager()->table_exists('local_proctopus_callback')) {
            $dup = callback_handler::handle($body, 'application/json', 'c3', $verifier);
            $this->assertTrue(!empty($dup['body']['duplicate']) || $dup['http'] === 409);
        }

        // Ensure no full raw JWT etc stored.
        if ($DB->get_manager()->table_exists('local_proctopus_callback')) {
            $rows = $DB->get_records('local_proctopus_callback', null, 'id DESC', '*', 0, 1);
            $row = reset($rows);
            if ($row) {
                $this->assertNotEmpty($row->payload_hash);
                $this->assertStringNotContainsString($body, json_encode($row));
            }
        }
    }

    public function test_no_quiz_attempts_insert_in_sources(): void {
        $dir = dirname(__DIR__);
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($it as $f) {
            if (!$f->isFile() || $f->getExtension() !== 'php') {
                continue;
            }
            if (str_contains($f->getPathname(), DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR)) {
                continue;
            }
            $src = file_get_contents($f->getPathname());
            $this->assertStringNotContainsString("insert_record('quiz_attempts'", $src, $f->getPathname());
            $this->assertStringNotContainsString('uniqueid  = 90', $src);
            $this->assertStringNotContainsString('dl.kaznu.edu.kz', $src);
            $this->assertStringNotContainsString('kaznu_moodle', $src);
        }
    }

    public function test_open_redirect_and_xss(): void {
        $u = local_proctopus_local_url('https://evil.example/x');
        $this->assertStringNotContainsString('evil.example', $u->out(false));
        $msg = get_string('proctopus_start_failed', 'local_proctopus');
        $this->assertStringNotContainsString('<script>', $msg);
        $this->assertFalse(local_proctopus_is_redirect_host_allowed('http://farabi-proctoring.kaznu.kz/x'));
        $this->assertTrue(local_proctopus_is_redirect_host_allowed('https://farabi-proctoring.kaznu.kz/x'));
    }

    public function test_legacy_client_redacts_token_in_stub_log(): void {
        $stub = new stub_transport();
        $stub->redirecturl = 'https://farabi-proctoring.kaznu.kz/ui/session';
        $client = new \local_proctopus\api\legacy_proctopus_client($stub);
        $r = $client->start('header.payload.sig', 'corr');
        $this->assertTrue($r['ok']);
        $this->assertStringNotContainsString('header.payload.sig', $stub->calls[0]['url']);
        $this->assertStringContainsString('[redacted]', $stub->calls[0]['url']);
    }
}
