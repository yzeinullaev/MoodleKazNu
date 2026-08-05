<?php
namespace local_kaznu\local;

defined('MOODLE_INTERNAL') || die();

/**
 * HTTPS gate for Univer credential handling.
 */
final class https_gate {
    /**
     * True when Moodle wwwroot and the current request are HTTPS.
     */
    public static function is_secure(): bool {
        global $CFG;
        if (empty($CFG->wwwroot) || !str_starts_with(\core_text::strtolower($CFG->wwwroot), 'https://')) {
            return false;
        }
        if (function_exists('is_https') && !is_https()) {
            return false;
        }
        return true;
    }

    /**
     * Block credential processing when public HTTP (or mismatched wwwroot) is detected.
     */
    public static function assert_secure_or_throw(): void {
        if (!self::is_secure()) {
            throw new \moodle_exception('univer_https_required_login', 'local_kaznu');
        }
    }
}
