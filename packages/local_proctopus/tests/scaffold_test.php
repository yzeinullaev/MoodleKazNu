<?php
namespace local_proctopus;

use local_proctopus\http\stub_transport;
use local_proctopus\jwt\hs_token_service;
use local_proctopus\service\callback_handler;
use local_proctopus\service\session_service;

defined('MOODLE_INTERNAL') || die();

/**
 * Offline scaffolding tests (no real HTTP).
 * @covers \local_proctopus\jwt\hs_token_service
 * @covers \local_proctopus\service\callback_handler
 */
class scaffold_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('proctopus_enabled', 0, 'local_proctopus');
        set_config('proctopus_account_name', 'farabi_business', 'local_proctopus');
    }

    public function test_disabled_mode(): void {
        $r = callback_handler::handle('{}', 'application/json', 'c1');
        $this->assertSame(503, $r['http']);
        $this->assertSame('integration_disabled', $r['body']['error']);
    }

    public function test_account_name_default(): void {
        $this->assertSame('farabi_business', local_proctopus_account_name());
    }

    public function test_jwt_required_claims(): void {
        $svc = new hs_token_service();
        $c = $svc->build_claims('s1', 'farabi_business', 120);
        $this->assertArrayHasKey('iat', $c);
        $this->assertArrayHasKey('nbf', $c);
        $this->assertArrayHasKey('exp', $c);
        $this->assertArrayHasKey('jti', $c);
        $this->assertSame('farabi_business', $c['accountName']);
    }

    public function test_jwt_encode_and_secret_absent_from_token_string(): void {
        $svc = new hs_token_service();
        $secret = 'super-secret-value-xyz';
        $token = $svc->encode($svc->build_claims('s2', 'farabi_business', 120), $secret, 'HS256');
        $this->assertStringNotContainsString($secret, $token);
        $this->assertSame(2, substr_count($token, '.'));
    }

    public function test_no_legacy_domains_in_local_url(): void {
        global $CFG;
        $u = local_proctopus_local_url('/local/proctopus/callback.php');
        $out = $u->out(false);
        $this->assertStringNotContainsString('dl.kaznu.edu.kz', $out);
        $this->assertStringNotContainsString('kaznu_moodle', $out);
        $this->assertStringStartsWith($CFG->wwwroot, $out);
    }

    public function test_stub_session_create(): void {
        set_config('proctopus_enabled', 1, 'local_proctopus');
        set_config('proctopus_jwt_algorithm', 'HS256', 'local_proctopus');
        putenv('PROCTOPUS_JWT_SECRET=phpunit-only-secret');
        $user = $this->getDataGenerator()->create_user();
        // Tables may not exist if plugin not installed in test site; only when installed.
        global $DB;
        if (!$DB->get_manager()->table_exists('local_proctopus_session')) {
            $this->markTestSkipped('local_proctopus tables not installed');
        }
        $svc = new session_service(null, new stub_transport());
        $out = $svc->create_session((int) $user->id, 2, 3, 4);
        $this->assertArrayHasKey('sessionid', $out);
        $this->assertSame('farabi_business', local_proctopus_account_name());
        $again = $svc->create_session((int) $user->id, 2, 3, 4);
        $this->assertTrue(!empty($again['duplicate']));
        putenv('PROCTOPUS_JWT_SECRET');
    }

    public function test_invalid_callback_when_enabled(): void {
        set_config('proctopus_enabled', 1, 'local_proctopus');
        $r = callback_handler::handle('{"x":1}', 'application/json', 'c2');
        $this->assertContains($r['http'], [401, 503]);
        $this->assertFalse($r['body']['ok']);
    }

    public function test_open_redirect_blocked_path(): void {
        $u = local_proctopus_local_url('https://evil.example/phish');
        $this->assertStringContainsString('/local/proctopus/', $u->out(false));
        $this->assertStringNotContainsString('evil.example', $u->out(false));
    }

    public function test_xss_escape_lang(): void {
        $s = get_string('proctopus_not_configured', 'local_proctopus');
        $this->assertStringNotContainsString('<script>', $s);
        $this->assertSame($s, s($s));
    }

    public function test_no_quiz_attempts_insert_in_plugin_tree(): void {
        // Source hygiene: callback_handler and session_service must not raw-insert quiz_attempts.
        $dir = dirname(__DIR__);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($files as $f) {
            if (!$f->isFile() || $f->getExtension() !== 'php') {
                continue;
            }
            $src = file_get_contents($f->getPathname());
            $this->assertStringNotContainsString("insert_record('quiz_attempts'", $src);
            $this->assertStringNotContainsString('insert_record("quiz_attempts"', $src);
        }
    }
}
