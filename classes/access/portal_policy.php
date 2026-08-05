<?php
namespace local_kaznu\access;

defined('MOODLE_INTERNAL') || die();

/**
 * Portal access policy (Variant A).
 *
 * Concepts:
 *  - Portal user (ordinary Farabi School user): local/kaznu:viewportal
 *    → unified cabinet, common tabs, forced off /my/
 *  - Univer-linked data: local_kaznu_univer_user OR local/kaznu:viewuniverdata
 *    → extra tabs only
 *
 * Never uses username, auth method, or login session origin.
 */
final class portal_policy {
    /** @deprecated Use ROLE_PORTAL_USER / ROLE_UNIVER_STUDENT */
    public const ROLE_SHORTNAME = 'farabi_student';

    public const ROLE_PORTAL_USER = 'farabi_portal_user';
    public const ROLE_UNIVER_STUDENT = 'farabi_student';

    /**
     * Ordinary portal user: sees unified cabinet. Not staff/admin.
     */
    public static function is_portal_user(?\stdClass $user = null): bool {
        global $USER;

        $user = $user ?? $USER;
        if (empty($user->id) || (int) $user->id <= 0) {
            return false;
        }
        if (function_exists('isguestuser') && isguestuser($user)) {
            return false;
        }
        if (self::is_staff_like($user)) {
            return false;
        }

        $sys = \context_system::instance();
        if (has_capability('local/kaznu:viewportal', $sys, $user, false)) {
            return true;
        }
        // Legacy Phase 6A capability.
        if (has_capability('local/kaznu:portalstudent', $sys, $user, false)) {
            return true;
        }
        return false;
    }

    /**
     * @deprecated Use is_portal_user()
     */
    public static function is_portal_student(?\stdClass $user = null): bool {
        return self::is_portal_user($user);
    }

    /**
     * Whether Univer cabinet modules (schedule/grades/attendance) are available.
     * Based on permanent link or explicit capability — not login method.
     */
    public static function can_view_univer_data(?\stdClass $user = null): bool {
        global $USER, $DB;

        $user = $user ?? $USER;
        if (empty($user->id) || (int) $user->id <= 0) {
            return false;
        }
        if (self::is_staff_like($user)) {
            // Staff use standard Moodle; no student Univer tabs by default.
            return false;
        }
        $sys = \context_system::instance();
        if (has_capability('local/kaznu:viewuniverdata', $sys, $user, false)) {
            return true;
        }
        return $DB->record_exists('local_kaznu_univer_user', ['userid' => (int) $user->id]);
    }

    /**
     * Staff / managers / teachers: keep standard Moodle dashboard.
     * Course-level student enrolment alone does NOT make someone portal or staff.
     */
    public static function is_staff_like(?\stdClass $user = null): bool {
        global $USER, $DB;

        $user = $user ?? $USER;
        if (empty($user->id)) {
            return true;
        }
        // Ensure capability checks have a full user record when only id is provided.
        if (!isset($user->username)) {
            $full = \core_user::get_user((int) $user->id, '*', IGNORE_MISSING);
            if ($full) {
                $user = $full;
            }
        }
        if (function_exists('is_siteadmin') && is_siteadmin($user)) {
            return true;
        }

        $sys = \context_system::instance();
        if (has_capability('moodle/site:config', $sys, $user, false)
                || has_capability('moodle/site:configview', $sys, $user, false)
                || has_capability('moodle/course:create', $sys, $user, false)
                || has_capability('moodle/site:approvecourse', $sys, $user, false)
                || has_capability('moodle/site:manageblocks', $sys, $user, false)) {
            return true;
        }

        // Teaching / management roles in any context → keep standard Moodle (not portal).
        $staffshorts = ['manager', 'coursecreator', 'editingteacher', 'teacher'];
        list($insql, $params) = $DB->get_in_or_equal($staffshorts, SQL_PARAMS_NAMED, 'sh');
        $params['uid'] = (int) $user->id;
        $sql = "SELECT 1
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.userid = :uid AND r.shortname {$insql}";
        return $DB->record_exists_sql($sql, $params);
    }

    /**
     * Exempt from student portal path restrictions (same as not portal user for guards).
     */
    public static function is_staff_exempt(?\stdClass $user = null): bool {
        return !self::is_portal_user($user);
    }
}
