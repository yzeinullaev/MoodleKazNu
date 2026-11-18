<?php
namespace local_kaznu;

use local_kaznu\access\portal_policy;
use local_kaznu\local\portal_role;
use local_kaznu\univer_status;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_kaznu\access\portal_policy
 * @covers \local_kaznu\local\portal_role
 */
class portal_policy_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        portal_role::ensure_all_roles();
    }

    public function test_portal_user_not_admin(): void {
        $student = $this->getDataGenerator()->create_user(['auth' => 'manual']);
        portal_role::assign_portal_user((int) $student->id);
        $this->assertTrue(portal_policy::is_portal_user($student));
        $this->assertFalse(portal_policy::can_view_univer_data($student));

        $admin = get_admin();
        $this->assertFalse(portal_policy::is_portal_user($admin));
        $this->assertTrue(portal_policy::is_staff_like($admin));
    }

    public function test_ordinary_user_becomes_portal_on_assign_batch(): void {
        $user = $this->getDataGenerator()->create_user(['auth' => 'manual']);
        $this->assertFalse(portal_policy::is_portal_user($user));
        portal_role::assign_ordinary_portal_users();
        $user = \core_user::get_user($user->id);
        $this->assertTrue(portal_policy::is_portal_user($user));
        $this->assertFalse(portal_policy::can_view_univer_data($user));
    }

    public function test_linked_user_gets_univer_tabs_capability(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['auth' => 'nologin']);
        $DB->insert_record('local_kaznu_univer_user', (object) [
            'userid' => $user->id,
            'univer_userid' => 700000 + (int) $user->id,
            'univer_login' => $user->username,
            'faculty' => 'F',
            'speciality' => 'S',
            'course_number' => 1,
            'group_name' => 'G1',
            'current_year' => 2025,
            'current_semester' => 1,
            'email_is_technical' => 1,
            'status' => univer_status::ACTIVE,
            'lastsync' => time(),
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        portal_role::assign_all_linked();
        $user = \core_user::get_user($user->id);
        $this->assertTrue(portal_policy::is_portal_user($user));
        $this->assertTrue(portal_policy::can_view_univer_data($user));
    }

    public function test_is_portal_student_alias(): void {
        $student = $this->getDataGenerator()->create_user(['auth' => 'manual']);
        portal_role::assign_portal_user((int) $student->id);
        $this->assertTrue(portal_policy::is_portal_student($student));
    }
}
