<?php
namespace local_kaznu\service;

use local_kaznu\api\dto\login_result;
use local_kaznu\api\dto\semester_info;
use local_kaznu\api\dto\student_profile;
use local_kaznu\api\dto\subject;
use local_kaznu\api\univer_exception;
use local_kaznu\repository\univer_subject_repository;
use local_kaznu\repository\univer_user_repository;
use local_kaznu\univer_status;

defined('MOODLE_INTERNAL') || die();

/**
 * JIT provision / update Moodle users from Univer (no password storage).
 *
 * Update policy for mdl_user:
 * - username: set only on create; never renamed later.
 * - firstname/lastname: updated when empty, OR previous email was technical,
 *   OR admin setting univer_auth_update is enabled.
 * - email: technical @users.invalid always refreshed; real emails never overwritten by stub.
 * - Plugin cache fields (faculty, …): always updated from latest successful sync.
 */
class user_service {
    private univer_user_repository $userrepo;
    private univer_subject_repository $subjectrepo;

    public function __construct(
        ?univer_user_repository $userrepo = null,
        ?univer_subject_repository $subjectrepo = null
    ) {
        $this->userrepo = $userrepo ?? new univer_user_repository();
        $this->subjectrepo = $subjectrepo ?? new univer_subject_repository();
    }

    /**
     * Find or create Moodle user and upsert Univer link + optional subjects.
     *
     * Lookup order: univer_user_id → linked Moodle userid → username (initial/migration only).
     *
     * @param subject[] $subjectlist
     * @return array{user:\stdClass,created:bool,subjects:array}
     */
    public function upsert_from_univer(
        login_result $login,
        student_profile $profile,
        ?semester_info $semester = null,
        array $subjectlist = []
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/lib.php');

        if (!$login->is_student()) {
            throw new univer_exception('univer_not_student');
        }

        $trans = $DB->start_delegated_transaction();
        try {
            $existinglink = $this->userrepo->get_by_univer_userid($login->univeruserid);
            $user = null;
            $created = false;

            if ($existinglink) {
                $user = $DB->get_record('user', ['id' => $existinglink->userid, 'deleted' => 0], '*', MUST_EXIST);
            } else {
                // Username only for initial match / migration.
                $byname = $DB->get_record('user', [
                    'username' => \core_text::strtolower($login->login),
                    'mnethostid' => $CFG->mnet_localhost_id,
                    'deleted' => 0,
                ]);
                if ($byname) {
                    $otherlink = $this->userrepo->get_by_moodle_userid((int) $byname->id);
                    if ($otherlink && (int) $otherlink->univer_userid !== $login->univeruserid) {
                        throw new univer_exception('univer_username_conflict', '', null, $login->login);
                    }
                    $user = $byname;
                }
            }

            $email = $this->resolve_email($login, $profile);
            $emailtech = str_ends_with($email, '@users.invalid');

            if (!$user) {
                $user = $this->create_moodle_user($login, $profile, $email, $emailtech);
                $created = true;
            } else {
                $this->update_moodle_user($user, $profile, $email, $emailtech);
                $user = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
            }

            $this->upsert_link((int) $user->id, $login, $profile, $semester, $emailtech);

            // Portal mode role (system context). Outside of nologin/auth migration.
            try {
                \local_kaznu\local\portal_role::assign_univer_student((int) $user->id);
            } catch (\Throwable $e) {
                debugging('portal_role assign failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }

            $subjectstats = ['upserted' => 0, 'reactivated' => 0, 'staled' => 0];
            if ($semester && $subjectlist !== []) {
                $subjectstats = $this->subjectrepo->sync_period(
                    (int) $user->id,
                    $semester->currentyear,
                    $semester->currentsemester,
                    $subjectlist
                );
            }

            $trans->allow_commit();
            return [
                'user' => $user,
                'created' => $created,
                'subjects' => $subjectstats,
            ];
        } catch (\Throwable $e) {
            $trans->rollback($e);
            throw $e;
        }
    }

    /**
     * Remove plugin rows when a Moodle user is deleted.
     */
    public function purge_for_userid(int $userid): void {
        global $DB;
        $this->subjectrepo->delete_for_userid($userid);
        $this->userrepo->delete_for_userid($userid);
        $DB->delete_records('local_kaznu_xp', ['userid' => $userid]);
        if ($DB->get_manager()->table_exists('local_kaznu_sync_log')) {
            $DB->delete_records('local_kaznu_sync_log', ['userid' => $userid]);
        }
        if ($DB->get_manager()->table_exists('local_kaznu_schedule')) {
            $DB->delete_records('local_kaznu_schedule', ['userid' => $userid]);
        }
        if ($DB->get_manager()->table_exists('local_kaznu_assessment')) {
            $DB->delete_records('local_kaznu_assessment', ['userid' => $userid]);
        }
        if ($DB->get_manager()->table_exists('local_kaznu_attendance')) {
            $DB->delete_records('local_kaznu_attendance', ['userid' => $userid]);
        }
        if ($DB->get_manager()->table_exists('local_kaznu_cache_state')) {
            $DB->delete_records('local_kaznu_cache_state', ['userid' => $userid]);
        }
    }

    private function resolve_email(login_result $login, student_profile $profile): string {
        if ($profile->email && !$profile->emailistechnical && str_contains($profile->email, '@')) {
            return \core_text::strtolower(trim($profile->email));
        }
        return $login->technical_email();
    }

    private function create_moodle_user(
        login_result $login,
        student_profile $profile,
        string $email,
        bool $emailtech
    ): \stdClass {
        global $CFG, $DB;

        $username = \core_text::strtolower(trim($login->login));
        if ($DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id])) {
            throw new univer_exception('univer_username_conflict', '', null, $username);
        }

        if ($DB->record_exists('user', [
            'email' => $email,
            'mnethostid' => $CFG->mnet_localhost_id,
            'deleted' => 0,
        ])) {
            $email = 'univer_' . $login->univeruserid . '_' . time() . '@users.invalid';
            $emailtech = true;
        }

        // auth=univer: identity via auth_univer plugin (Univer API); local password hash unused.
        $user = (object) [
            'auth' => class_exists('auth_plugin_univer') || file_exists($CFG->dirroot . '/auth/univer/auth.php')
                ? 'univer'
                : 'nologin',
            'confirmed' => 1,
            'policyagreed' => 1,
            'deleted' => 0,
            'suspended' => 0,
            'mnethostid' => $CFG->mnet_localhost_id,
            'username' => $username,
            'password' => hash_internal_user_password(random_string(24)),
            'firstname' => $this->nonempty($profile->firstname, $profile->firstnameen, $username),
            'lastname' => $this->nonempty($profile->lastname, $profile->lastnameen, $username),
            'email' => $email,
            'emailstop' => $emailtech ? 1 : 0,
            'lang' => current_language(),
            'calendartype' => $CFG->calendartype,
            'timezone' => $CFG->timezone ?? 99,
            'mailformat' => 1,
            'maildisplay' => 0,
            'city' => '',
            'country' => '',
        ];

        $userid = user_create_user($user, false, false);
        return $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    }

    private function update_moodle_user(
        \stdClass $user,
        student_profile $profile,
        string $email,
        bool $emailtech
    ): void {
        global $DB;

        $update = false;
        $link = $this->userrepo->get_by_moodle_userid((int) $user->id);
        $linktech = $link ? ((int) $link->email_is_technical === 1) : false;
        $allowname = empty($user->firstname) || empty($user->lastname)
            || $linktech
            || (bool) get_config('local_kaznu', 'univer_auth_update');

        if ($allowname) {
            $fn = $this->nonempty($profile->firstname, $profile->firstnameen, $user->firstname);
            $ln = $this->nonempty($profile->lastname, $profile->lastnameen, $user->lastname);
            if ($fn !== $user->firstname || $ln !== $user->lastname) {
                $user->firstname = $fn;
                $user->lastname = $ln;
                $update = true;
            }
        }

        $currenttech = str_ends_with((string) $user->email, '@users.invalid');
        if ($currenttech) {
            if ($email !== $user->email) {
                if (!$DB->record_exists_select(
                    'user',
                    "email = :email AND id <> :id AND deleted = 0 AND mnethostid = :m",
                    ['email' => $email, 'id' => $user->id, 'm' => $user->mnethostid]
                )) {
                    $user->email = $email;
                    $update = true;
                }
            }
            if ((int) $user->emailstop !== 1) {
                $user->emailstop = 1;
                $update = true;
            }
        } else if (empty($user->email) && $email !== '') {
            $user->email = $email;
            $user->emailstop = $emailtech ? 1 : 0;
            $update = true;
        }
        // Real non-empty emails are never overwritten by a stub.

        if ($update) {
            user_update_user($user, false, false);
        }
    }

    private function upsert_link(
        int $userid,
        login_result $login,
        student_profile $profile,
        ?semester_info $semester,
        bool $emailtech
    ): void {
        $link = $this->userrepo->get_by_univer_userid($login->univeruserid);
        if (!$link) {
            $link = $this->userrepo->get_by_moodle_userid($userid);
        }

        $now = time();
        $data = (object) [
            'userid' => $userid,
            'univer_userid' => $login->univeruserid,
            'univer_login' => $login->login,
            'faculty' => $profile->faculty,
            'faculty_id' => $profile->facultyid !== null ? (int) $profile->facultyid : null,
            'speciality' => $profile->speciality,
            'course_number' => $profile->coursenumber,
            'group_name' => $profile->groupname,
            'current_year' => $semester ? $semester->currentyear : 0,
            'current_semester' => $semester ? $semester->currentsemester : 0,
            'email_is_technical' => $emailtech ? 1 : 0,
            'status' => univer_status::ACTIVE,
            'lastsync' => $now,
        ];

        if ($link) {
            if ((int) $link->userid !== $userid) {
                throw new univer_exception('univer_username_conflict', 'Link userid mismatch');
            }
            $data->id = $link->id;
            $this->userrepo->update($data);
        } else {
            $this->userrepo->insert($data);
        }
    }

    private function nonempty(?string ...$candidates): string {
        foreach ($candidates as $c) {
            if ($c === null) {
                continue;
            }
            $c = trim($c);
            if ($c !== '') {
                return $c;
            }
        }
        return 'Student';
    }
}
