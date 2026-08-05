<?php
/**
 * @package auth_univer
 * @covers \auth_plugin_univer
 */
namespace auth_univer;

defined('MOODLE_INTERNAL') || die();

class auth_univer_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        require_once($GLOBALS['CFG']->dirroot . '/auth/univer/auth.php');
        set_config('univer_unified_login_enabled', 1, 'local_kaznu');
        set_config('univer_enabled', 1, 'local_kaznu');
        set_config('univer_fallback_auth_methods', 'manual,email', 'local_kaznu');
    }

    public function test_plugin_constructs_and_prevents_local_passwords(): void {
        $auth = new \auth_plugin_univer();
        $this->assertSame('univer', $auth->authtype);
        $this->assertTrue($auth->prevent_local_passwords());
        $this->assertFalse($auth->can_change_password());
        $this->assertFalse($auth->can_reset_password());
        $this->assertSame([], $auth->loginpage_idp_list(''));
    }

    public function test_local_fallback_only_allowlisted_methods(): void {
        $auth = new \auth_plugin_univer();

        $manual = $this->getDataGenerator()->create_user(['auth' => 'manual', 'username' => 'staff_manual']);
        $this->assertTrue($auth->local_fallback_allowed('staff_manual'));

        $email = $this->getDataGenerator()->create_user(['auth' => 'email', 'username' => 'staff_email']);
        $this->assertTrue($auth->local_fallback_allowed('staff_email'));

        $nologin = $this->getDataGenerator()->create_user(['auth' => 'nologin', 'username' => 'portal_only']);
        $this->assertFalse($auth->local_fallback_allowed('portal_only'));

        $univer = $this->getDataGenerator()->create_user(['auth' => 'univer', 'username' => 'uni_user']);
        $this->assertFalse($auth->local_fallback_allowed('uni_user'));

        $this->assertFalse($auth->local_fallback_allowed('no_such_user_xyz'));
    }

    public function test_linked_user_no_local_fallback_even_if_manual(): void {
        global $DB;
        $auth = new \auth_plugin_univer();
        $u = $this->getDataGenerator()->create_user(['auth' => 'manual', 'username' => 'linked_manual']);
        if ($DB->get_manager()->table_exists('local_kaznu_univer_user')) {
            $DB->insert_record('local_kaznu_univer_user', (object) [
                'userid' => $u->id,
                'univer_userid' => 910001,
                'univer_login' => $u->username,
                'faculty' => '',
                'speciality' => '',
                'course_number' => 0,
                'group_name' => '',
                'current_year' => 0,
                'current_semester' => 0,
                'email_is_technical' => 1,
                'status' => 'active',
                'lastsync' => time(),
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }
        $this->assertFalse($auth->local_fallback_allowed('linked_manual'));
    }

    public function test_neutral_error_no_username(): void {
        $auth = new \auth_plugin_univer();
        $m = new \ReflectionMethod($auth, 'neutral_error');
        $m->setAccessible(true);
        $r = new \local_kaznu\local\login_result(
            \local_kaznu\local\login_result::ACCOUNT_REVIEW,
            null,
            false,
            'corr',
            'x'
        );
        $msg = $m->invoke($auth, $r);
        $this->assertStringNotContainsString('corr', $msg);
        $this->assertNotSame('', $msg);
    }
}
