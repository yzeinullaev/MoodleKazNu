<?php
namespace local_kaznu;

use local_kaznu\api\dto\login_result;
use local_kaznu\api\dto\semester_info;
use local_kaznu\api\dto\student_profile;
use local_kaznu\api\dto\subject;
use local_kaznu\api\univer_exception;
use local_kaznu\repository\univer_subject_repository;
use local_kaznu\service\user_service;

defined('MOODLE_INTERNAL') || die();

/**
 * Subject repo that fails after parent would have written user+link (transaction test).
 */
class user_service_test_failing_subjects extends univer_subject_repository {
    public function sync_period(int $userid, int $year, int $semester, array $subjects): array {
        throw new \RuntimeException('forced sync failure');
    }
}

/**
 * @covers \local_kaznu\service\user_service
 * @covers \local_kaznu\repository\univer_user_repository
 * @covers \local_kaznu\repository\univer_subject_repository
 */
class user_service_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('univer_auth_update', 0, 'local_kaznu');
    }

    private function login(int $uid = 297051, string $username = 'oxikbayev.yernur'): login_result {
        return new login_result($uid, $username, 'STUDENT', 4194304, 'sess', 'ok');
    }

    private function profile(
        int $uid = 297051,
        string $username = 'oxikbayev.yernur',
        ?string $email = null,
        bool $emailtech = true,
        string $firstname = 'Yernur',
        string $lastname = 'Oxikbayev'
    ): student_profile {
        return new student_profile(
            $uid,
            $username,
            $firstname,
            $lastname,
            '',
            $email,
            $emailtech,
            'Faculty',
            'Speciality',
            2,
            'Group-A',
            'Yernur',
            'Oxikbayev'
        );
    }

    private function semester(int $year = 2025, int $sem = 1): semester_info {
        return new semester_info($year, $sem, 8, 'RK1');
    }

    private function subject(int $id, string $name, int $year = 2025, int $sem = 1): subject {
        return new subject($id, $name, 10, 20, $year, $sem, 1, 'Lecture', 0, 1);
    }

    public function test_create_new_user_with_technical_email(): void {
        $svc = new user_service();
        $result = $svc->upsert_from_univer($this->login(), $this->profile());

        $this->assertTrue($result['created']);
        $user = $result['user'];
        $this->assertSame('oxikbayev.yernur', $user->username);
        $this->assertSame('univer_297051@users.invalid', $user->email);
        $this->assertSame(1, (int) $user->emailstop);
        $this->assertSame('Yernur', $user->firstname);

        global $DB;
        $link = $DB->get_record('local_kaznu_univer_user', ['univer_userid' => 297051], '*', MUST_EXIST);
        $this->assertEquals($user->id, $link->userid);
        $this->assertSame(1, (int) $link->email_is_technical);
        $this->assertGreaterThan(0, (int) $link->lastsync);
    }

    public function test_resync_does_not_duplicate(): void {
        $svc = new user_service();
        $first = $svc->upsert_from_univer($this->login(), $this->profile());
        $second = $svc->upsert_from_univer(
            $this->login(),
            $this->profile(email: null, firstname: 'Yernur', lastname: 'Oxikbayev')
        );

        $this->assertFalse($second['created']);
        $this->assertEquals($first['user']->id, $second['user']->id);

        global $DB;
        $this->assertEquals(1, $DB->count_records('local_kaznu_univer_user', ['univer_userid' => 297051]));
        $this->assertEquals(1, $DB->count_records('user', [
            'username' => 'oxikbayev.yernur',
            'deleted' => 0,
        ]));
    }

    public function test_profile_update_when_auth_update_enabled(): void {
        set_config('univer_auth_update', 1, 'local_kaznu');
        $svc = new user_service();
        $svc->upsert_from_univer($this->login(), $this->profile());
        $svc->upsert_from_univer(
            $this->login(),
            $this->profile(firstname: 'Updated', lastname: 'Name')
        );

        global $DB;
        $user = $DB->get_record('user', ['username' => 'oxikbayev.yernur'], '*', MUST_EXIST);
        $this->assertSame('Updated', $user->firstname);
        $this->assertSame('Name', $user->lastname);

        $link = $DB->get_record('local_kaznu_univer_user', ['univer_userid' => 297051], '*', MUST_EXIST);
        $this->assertSame('Faculty', $link->faculty);
    }

    public function test_manual_name_preserved_when_auth_update_off(): void {
        $svc = new user_service();
        $created = $svc->upsert_from_univer($this->login(), $this->profile());

        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        $user = $DB->get_record('user', ['id' => $created['user']->id], '*', MUST_EXIST);
        // Simulate admin/manual correction + real mailbox.
        $user->firstname = 'Manual';
        $user->lastname = 'Fix';
        $user->email = 'real.student@example.com';
        $user->emailstop = 0;
        user_update_user($user, false, false);
        $DB->set_field('local_kaznu_univer_user', 'email_is_technical', 0, ['userid' => $user->id]);

        $svc->upsert_from_univer(
            $this->login(),
            $this->profile(email: null, emailtech: true, firstname: 'FromUniver', lastname: 'API')
        );

        $user = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
        $this->assertSame('Manual', $user->firstname);
        $this->assertSame('Fix', $user->lastname);
        $this->assertSame('real.student@example.com', $user->email);
    }

    public function test_missing_email_uses_users_invalid_stub(): void {
        $svc = new user_service();
        $result = $svc->upsert_from_univer(
            $this->login(1001, 'nostudent.mail'),
            $this->profile(1001, 'nostudent.mail', null, true)
        );
        $this->assertSame('univer_1001@users.invalid', $result['user']->email);
        $this->assertSame(1, (int) $result['user']->emailstop);
    }

    public function test_username_conflict_with_other_univer_link(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');

        $existing = $this->getDataGenerator()->create_user([
            'username' => 'taken.login',
            'email' => 'taken@example.com',
        ]);
        $DB->insert_record('local_kaznu_univer_user', (object) [
            'userid' => $existing->id,
            'univer_userid' => 111,
            'univer_login' => 'taken.login',
            'faculty' => '',
            'speciality' => '',
            'course_number' => 0,
            'group_name' => '',
            'current_year' => 0,
            'current_semester' => 0,
            'email_is_technical' => 0,
            'status' => univer_status::ACTIVE,
            'lastsync' => time(),
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $svc = new user_service();
        $this->expectException(univer_exception::class);
        $svc->upsert_from_univer(
            $this->login(999, 'taken.login'),
            $this->profile(999, 'taken.login')
        );
    }

    public function test_subject_upsert_stale_and_reactivate(): void {
        $svc = new user_service();
        $sem = $this->semester();
        $first = $svc->upsert_from_univer(
            $this->login(),
            $this->profile(),
            $sem,
            [
                $this->subject(501, 'Math'),
                $this->subject(502, 'Physics'),
            ]
        );
        $userid = (int) $first['user']->id;
        $this->assertSame(2, $first['subjects']['upserted']);

        global $DB;
        $this->assertEquals(2, $DB->count_records('local_kaznu_univer_subject', [
            'userid' => $userid,
            'status' => univer_status::ACTIVE,
        ]));

        // Physics disappears → stale, not deleted.
        $second = $svc->upsert_from_univer(
            $this->login(),
            $this->profile(),
            $sem,
            [$this->subject(501, 'Math Advanced')]
        );
        $this->assertSame(1, $second['subjects']['staled']);
        $physics = $DB->get_record('local_kaznu_univer_subject', [
            'userid' => $userid,
            'subjectid' => 502,
        ], '*', MUST_EXIST);
        $this->assertSame(univer_status::STALE, $physics->status);
        $this->assertEquals(2, $DB->count_records('local_kaznu_univer_subject', ['userid' => $userid]));

        $math = $DB->get_record('local_kaznu_univer_subject', [
            'userid' => $userid,
            'subjectid' => 501,
        ], '*', MUST_EXIST);
        $this->assertSame('Math Advanced', $math->subjectname);
        $this->assertSame('UNIVER:501:2025:1', $math->course_idnumber);

        // Physics returns → reactivated.
        $third = $svc->upsert_from_univer(
            $this->login(),
            $this->profile(),
            $sem,
            [
                $this->subject(501, 'Math Advanced'),
                $this->subject(502, 'Physics'),
            ]
        );
        $this->assertSame(1, $third['subjects']['reactivated']);
        $physics = $DB->get_record('local_kaznu_univer_subject', [
            'userid' => $userid,
            'subjectid' => 502,
        ], '*', MUST_EXIST);
        $this->assertSame(univer_status::ACTIVE, $physics->status);
    }

    public function test_transaction_rollback_on_subject_failure(): void {
        $svc = new user_service(null, new user_service_test_failing_subjects());
        $sem = $this->semester();

        try {
            $svc->upsert_from_univer(
                $this->login(4242, 'rollback.user'),
                $this->profile(4242, 'rollback.user'),
                $sem,
                [$this->subject(1, 'X')]
            );
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forced sync failure', $e->getMessage());
        }

        global $DB;
        $this->assertFalse($DB->record_exists('user', ['username' => 'rollback.user', 'deleted' => 0]));
        $this->assertFalse($DB->record_exists('local_kaznu_univer_user', ['univer_userid' => 4242]));
        $this->assertEquals(0, $DB->count_records('local_kaznu_univer_subject'));
    }

    public function test_purge_on_user_deleted(): void {
        $svc = new user_service();
        $result = $svc->upsert_from_univer(
            $this->login(),
            $this->profile(),
            $this->semester(),
            [$this->subject(1, 'A')]
        );
        $userid = (int) $result['user']->id;

        global $DB;
        $DB->insert_record('local_kaznu_xp', (object) [
            'userid' => $userid,
            'xp' => 10,
            'level' => 1,
            'timemodified' => time(),
        ]);

        delete_user($DB->get_record('user', ['id' => $userid], '*', MUST_EXIST));

        $this->assertFalse($DB->record_exists('local_kaznu_univer_user', ['userid' => $userid]));
        $this->assertFalse($DB->record_exists('local_kaznu_univer_subject', ['userid' => $userid]));
        $this->assertFalse($DB->record_exists('local_kaznu_xp', ['userid' => $userid]));
    }
}
