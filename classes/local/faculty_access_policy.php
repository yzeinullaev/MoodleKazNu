<?php
namespace local_kaznu\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Faculty / allowlist gate for Univer student login.
 *
 * Business rule lives in settings, not hard-coded in login_service.
 * When univer_faculty_filter_enabled is off (default), evaluate() always allows.
 * Name/string faculty is never used as allow/deny criterion.
 */
final class faculty_access_policy {
    public const OUTCOME_FILTER_OFF = 'filter_off';
    public const OUTCOME_SUCCESS_BUSINESS = 'success_business_school';
    public const OUTCOME_SUCCESS_ALLOWLIST = 'success_allowlisted_user';
    public const OUTCOME_DENIED_FACULTY = 'denied_faculty';
    public const OUTCOME_MISSING_FACULTY = 'missing_faculty';

    /**
     * Feature flag. Default off — safe for deploy without production enforcement.
     */
    public static function is_enabled(): bool {
        return (int) get_config('local_kaznu', 'univer_faculty_filter_enabled') === 1;
    }

    /**
     * Normalize a username for allowlist comparison only (does not mutate Moodle username).
     */
    public static function normalize_login(string $login): string {
        return \core_text::strtolower(trim($login));
    }

    /**
     * @return int[] Positive faculty IDs from comma-separated setting.
     */
    public static function parse_allowed_faculty_ids(?string $raw = null): array {
        if ($raw === null) {
            $raw = (string) (get_config('local_kaznu', 'univer_allowed_faculty_ids') ?? '28');
        }
        $ids = [];
        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if ($part === '' || !preg_match('/^-?\d+$/', $part)) {
                continue;
            }
            $id = (int) $part;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    /**
     * @return string[] Normalized logins.
     */
    public static function parse_allowlist(?string $raw = null): array {
        if ($raw === null) {
            $raw = (string) (get_config('local_kaznu', 'univer_login_allowlist') ?? '');
        }
        $out = [];
        foreach (explode(',', $raw) as $part) {
            $n = self::normalize_login($part);
            if ($n !== '') {
                $out[$n] = $n;
            }
        }
        return array_values($out);
    }

    public static function is_login_allowlisted(string $login): bool {
        $n = self::normalize_login($login);
        if ($n === '') {
            return false;
        }
        return in_array($n, self::parse_allowlist(), true);
    }

    /**
     * Decide after credentials + STUDENT + profile are already validated.
     *
     * @param string $login Univer login used at auth (not necessarily Moodle username).
     * @param int|null $facultyid Numeric faculty id from profile, or null if absent/unparsed.
     * @return string One of OUTCOME_* constants.
     */
    public static function evaluate(string $login, ?int $facultyid): string {
        if (!self::is_enabled()) {
            return self::OUTCOME_FILTER_OFF;
        }
        if (self::is_login_allowlisted($login)) {
            return self::OUTCOME_SUCCESS_ALLOWLIST;
        }
        if ($facultyid === null || $facultyid <= 0) {
            return self::OUTCOME_MISSING_FACULTY;
        }
        $allowed = self::parse_allowed_faculty_ids();
        if ($allowed === []) {
            // Misconfigured empty allow-set under enforced filter → fail closed.
            return self::OUTCOME_DENIED_FACULTY;
        }
        if (in_array($facultyid, $allowed, true)) {
            return self::OUTCOME_SUCCESS_BUSINESS;
        }
        return self::OUTCOME_DENIED_FACULTY;
    }

    public static function is_deny_outcome(string $outcome): bool {
        return in_array($outcome, [
            self::OUTCOME_DENIED_FACULTY,
            self::OUTCOME_MISSING_FACULTY,
        ], true);
    }
}
