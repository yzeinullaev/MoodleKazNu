<?php
namespace local_kaznu\local;

use local_kaznu\access\portal_policy;

defined('MOODLE_INTERNAL') || die();

/**
 * Variant A roles:
 *  - farabi_portal_user  → local/kaznu:viewportal (ordinary portal)
 *  - farabi_student      → viewportal + viewuniverdata + legacy portalstudent
 *
 * Assignment rules (idempotent, limited):
 *  - Never siteadmin / staff_like
 *  - Ordinary: auth in manual|email|nologin|univer + not staff_like
 *  - Univer-linked: both roles (or farabi_student alone which includes portal)
 */
final class portal_role {
    public static function ensure_role(): int {
        self::ensure_portal_user_role();
        return self::ensure_univer_student_role();
    }

    public static function ensure_all_roles(): void {
        self::ensure_portal_user_role();
        self::ensure_univer_student_role();
    }

    public static function ensure_portal_user_role(): int {
        global $DB, $CFG;
        require_once($CFG->libdir . '/accesslib.php');

        $short = portal_policy::ROLE_PORTAL_USER;
        $existing = $DB->get_record('role', ['shortname' => $short]);
        if ($existing) {
            $roleid = (int) $existing->id;
        } else {
            $roleid = create_role(
                get_string('role_farabi_portal_user', 'local_kaznu'),
                $short,
                get_string('role_farabi_portal_user_desc', 'local_kaznu'),
                'user'
            );
            set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        }
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        $sys = \context_system::instance();
        assign_capability('local/kaznu:viewportal', CAP_ALLOW, $roleid, $sys->id, true);
        assign_capability('local/kaznu:thesisupload', CAP_ALLOW, $roleid, $sys->id, true);
        assign_capability('moodle/user:manageownfiles', CAP_PROHIBIT, $roleid, $sys->id, true);
        assign_capability('moodle/blog:create', CAP_PROHIBIT, $roleid, $sys->id, true);
        assign_capability('moodle/blog:view', CAP_PROHIBIT, $roleid, $sys->id, true);
        assign_capability('moodle/calendar:manageownentries', CAP_PROHIBIT, $roleid, $sys->id, true);
        $sys->mark_dirty();
        return $roleid;
    }

    public static function ensure_univer_student_role(): int {
        global $DB, $CFG;
        require_once($CFG->libdir . '/accesslib.php');

        $short = portal_policy::ROLE_UNIVER_STUDENT;
        $existing = $DB->get_record('role', ['shortname' => $short]);
        if ($existing) {
            $roleid = (int) $existing->id;
        } else {
            $roleid = create_role(
                get_string('role_farabi_student', 'local_kaznu'),
                $short,
                get_string('role_farabi_student_desc', 'local_kaznu'),
                'student'
            );
            set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        }
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        $sys = \context_system::instance();
        assign_capability('local/kaznu:viewportal', CAP_ALLOW, $roleid, $sys->id, true);
        assign_capability('local/kaznu:viewuniverdata', CAP_ALLOW, $roleid, $sys->id, true);
        assign_capability('local/kaznu:portalstudent', CAP_ALLOW, $roleid, $sys->id, true);
        assign_capability('local/kaznu:thesisupload', CAP_ALLOW, $roleid, $sys->id, true);
        assign_capability('moodle/user:manageownfiles', CAP_PROHIBIT, $roleid, $sys->id, true);
        assign_capability('moodle/blog:create', CAP_PROHIBIT, $roleid, $sys->id, true);
        assign_capability('moodle/blog:view', CAP_PROHIBIT, $roleid, $sys->id, true);
        assign_capability('moodle/calendar:manageownentries', CAP_PROHIBIT, $roleid, $sys->id, true);
        $sys->mark_dirty();
        return $roleid;
    }

    /**
     * Assign farabi_student (Univer-linked portal).
     */
    public static function assign_to_user(int $userid): bool {
        return self::assign_univer_student($userid);
    }

    public static function assign_portal_user(int $userid): bool {
        global $DB;
        if (!self::may_assign_portal($userid)) {
            return false;
        }
        $roleid = self::ensure_portal_user_role();
        $sys = \context_system::instance();
        role_assign($roleid, $userid, $sys->id);
        return true;
    }

    public static function assign_univer_student(int $userid): bool {
        global $DB;
        if (!self::may_assign_portal($userid)) {
            return false;
        }
        // Linked get both: univer role covers viewportal+viewuniverdata.
        $roleid = self::ensure_univer_student_role();
        $sys = \context_system::instance();
        role_assign($roleid, $userid, $sys->id);
        // Also ensure portal_user for dual-role visibility diagnostics (optional).
        self::assign_portal_user($userid);
        return true;
    }

    /**
     * Limited mass assign for ordinary accounts (not all users blindly).
     *
     * Scope: auth ∈ manual|email|nologin|univer, not deleted/suspended, not staff_like.
     *
     * @return array{portal:int,univer:int}
     */
    public static function assign_ordinary_portal_users(): array {
        global $DB;
        self::ensure_all_roles();
        $auths = ['manual', 'email', 'nologin', 'univer'];
        list($insql, $params) = $DB->get_in_or_equal($auths, SQL_PARAMS_NAMED, 'a');
        $ids = $DB->get_fieldset_sql(
            "SELECT u.id FROM {user} u
              WHERE u.deleted = 0 AND u.suspended = 0
                AND u.id > 1 AND u.auth {$insql}",
            $params
        );
        $portal = 0;
        $univer = 0;
        foreach ($ids as $uid) {
            $uid = (int) $uid;
            if (portal_policy::is_staff_like((object) ['id' => $uid])) {
                continue;
            }
            $linked = $DB->record_exists('local_kaznu_univer_user', ['userid' => $uid]);
            if ($linked) {
                if (self::assign_univer_student($uid)) {
                    $univer++;
                }
            } else {
                if (self::assign_portal_user($uid)) {
                    $portal++;
                }
            }
        }
        return ['portal' => $portal, 'univer' => $univer];
    }

    public static function assign_all_linked(): int {
        global $DB;
        self::ensure_all_roles();
        $ids = $DB->get_fieldset_sql(
            "SELECT u.id
               FROM {local_kaznu_univer_user} k
               JOIN {user} u ON u.id = k.userid
              WHERE u.deleted = 0 AND u.suspended = 0 AND k.userid > 0"
        );
        $n = 0;
        foreach ($ids as $uid) {
            if (self::assign_univer_student((int) $uid)) {
                $n++;
            }
        }
        return $n;
    }

    private static function may_assign_portal(int $userid): bool {
        global $DB;
        if ($userid <= 0) {
            return false;
        }
        $user = $DB->get_record('user', ['id' => $userid], 'id,deleted,suspended');
        if (!$user || (int) $user->deleted === 1 || (int) $user->suspended === 1) {
            return false;
        }
        if (function_exists('is_siteadmin') && is_siteadmin($userid)) {
            return false;
        }
        if (portal_policy::is_staff_like($user)) {
            return false;
        }
        return true;
    }

    public static function count_assignments(): int {
        return self::count_role_assignments(portal_policy::ROLE_UNIVER_STUDENT)
            + self::count_role_assignments(portal_policy::ROLE_PORTAL_USER);
    }

    public static function count_role_assignments(string $shortname): int {
        global $DB;
        $role = $DB->get_record('role', ['shortname' => $shortname]);
        if (!$role) {
            return 0;
        }
        $sys = \context_system::instance();
        return (int) $DB->count_records('role_assignments', [
            'roleid' => $role->id,
            'contextid' => $sys->id,
        ]);
    }

    public static function count_duplicate_assignments(): int {
        global $DB;
        $sys = \context_system::instance();
        $sql = "SELECT COUNT(*) FROM (
                    SELECT userid, roleid, COUNT(*) AS c
                      FROM {role_assignments}
                     WHERE contextid = :c
                  GROUP BY userid, roleid
                    HAVING COUNT(*) > 1
                ) d";
        return (int) $DB->count_records_sql($sql, ['c' => $sys->id]);
    }

    /**
     * Rollback helper: remove portal role assignments created by this plugin (system context).
     * Does not delete roles themselves.
     *
     * @return int rows removed
     */
    public static function unassign_all_portal_roles(): int {
        global $DB;
        $n = 0;
        $sys = \context_system::instance();
        foreach ([portal_policy::ROLE_PORTAL_USER, portal_policy::ROLE_UNIVER_STUDENT] as $short) {
            $role = $DB->get_record('role', ['shortname' => $short]);
            if (!$role) {
                continue;
            }
            $assigns = $DB->get_records('role_assignments', [
                'roleid' => $role->id,
                'contextid' => $sys->id,
            ], '', 'id,userid');
            foreach ($assigns as $a) {
                role_unassign($role->id, (int) $a->userid, $sys->id);
                $n++;
            }
        }
        return $n;
    }
}
