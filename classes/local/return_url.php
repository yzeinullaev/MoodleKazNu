<?php
namespace local_kaznu\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Allow only local Moodle return URLs (open-redirect safe).
 */
final class return_url {
    /**
     * Sanitize a user-supplied wantsurl. Invalid → student cabinet.
     */
    public static function sanitize(?string $wantsurl): \moodle_url {
        if (!self::is_local($wantsurl)) {
            return new \moodle_url('/local/kaznu/my.php');
        }
        $raw = trim((string) $wantsurl);
        if ($raw === '') {
            return new \moodle_url('/local/kaznu/my.php');
        }
        if (str_starts_with($raw, '/')) {
            return new \moodle_url($raw);
        }
        global $CFG;
        $target = parse_url($raw);
        $path = $target['path'] ?? '/';
        $params = [];
        if (!empty($target['query'])) {
            parse_str($target['query'], $params);
        }
        return new \moodle_url($path, $params);
    }

    /**
     * Whether the raw value is an acceptable local destination.
     */
    public static function is_local(?string $wantsurl): bool {
        global $CFG;

        if ($wantsurl === null || trim($wantsurl) === '') {
            return true;
        }
        $raw = trim($wantsurl);
        $lower = \core_text::strtolower($raw);

        if (str_starts_with($lower, 'javascript:')
            || str_starts_with($lower, 'data:')
            || str_starts_with($lower, 'vbscript:')
            || str_starts_with($raw, '//')
            || str_contains($raw, '\\')
            || str_contains($raw, "\0")) {
            return false;
        }

        // Relative local path only.
        if (str_starts_with($raw, '/')) {
            return !str_starts_with($raw, '//');
        }

        $www = parse_url($CFG->wwwroot);
        $target = parse_url($raw);
        if ($www === false || $target === false) {
            return false;
        }
        // Reject userinfo tricks: https://evil.example@moodle.host/
        if (isset($target['user']) || isset($target['pass'])) {
            return false;
        }
        if (empty($target['host']) || empty($www['host'])) {
            return false;
        }
        $scheme = \core_text::strtolower((string) ($target['scheme'] ?? ''));
        if ($scheme !== '' && !in_array($scheme, ['http', 'https'], true)) {
            return false;
        }
        return \core_text::strtolower($target['host']) === \core_text::strtolower($www['host']);
    }
}
