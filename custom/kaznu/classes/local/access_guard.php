<?php
namespace local_kaznu\local;

use local_kaznu\access\portal_policy;

defined('MOODLE_INTERNAL') || die();

/**
 * Server-side page access guard for portal students.
 *
 * Security is never CSS-only. Does not run for CLI / cron / AJAX / WS.
 */
final class access_guard {
    /**
     * Evaluate current request and redirect / forbid when needed.
     */
    public static function maybe_enforce(): void {
        if (self::should_skip_request()) {
            return;
        }
        if (!isloggedin() || isguestuser()) {
            return;
        }
        if (!portal_policy::is_portal_user()) {
            return;
        }

        $script = self::current_script();
        $path = self::current_path();

        // Student home: /my/ and mycourses → cabinet.
        if (self::is_my_home($script, $path)) {
            redirect(new \moodle_url('/local/kaznu/my.php'));
        }

        // Calendar UI → schedule tab.
        if (self::is_calendar($script, $path)) {
            redirect(new \moodle_url('/local/kaznu/my.php', ['section' => 'schedule']));
        }

        // Private files (not activity fileareas).
        if (self::is_private_files($script, $path)) {
            redirect(
                new \moodle_url('/local/kaznu/my.php'),
                get_string('portal_privatefiles_denied', 'local_kaznu'),
                null,
                \core\output\notification::NOTIFY_INFO
            );
        }

        // Moodle grades overview/mygrades → official Univer grades tab (Policy A).
        if (self::is_moodle_grades_ui($script, $path)) {
            redirect(new \moodle_url('/local/kaznu/my.php', ['section' => 'grades']));
        }

        // Blogs UI (if still reachable while disabled).
        if (self::is_blog($script, $path)) {
            redirect(new \moodle_url('/local/kaznu/my.php'));
        }
    }

    private static function should_skip_request(): bool {
        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return true;
        }
        if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
            return true;
        }
        if (defined('WS_SERVER') && WS_SERVER) {
            return true;
        }
        if (defined('NO_MOODLE_COOKIES') && NO_MOODLE_COOKIES) {
            return true;
        }
        // Behat / PHPUnit.
        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
            return true;
        }
        if (defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING) {
            // Skip path redirects during Behat (tests own navigation).
            return true;
        }
        // Pluginfile / draft uploads must not be blocked by path heuristics on accidental match.
        $script = self::current_script();
        if (str_contains($script, '/pluginfile.php')
                || str_contains($script, '/draftfile.php')
                || str_contains($script, '/repository/')
                || str_contains($script, '/lib/ajax/')
                || str_contains($script, '/lib/filelib.php')) {
            return true;
        }
        return false;
    }

    private static function current_script(): string {
        global $SCRIPT;
        $script = (string) ($SCRIPT ?? '');
        if ($script === '') {
            $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        }
        return str_replace('\\', '/', $script);
    }

    private static function current_path(): string {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = parse_url($uri, PHP_URL_PATH);
        return is_string($path) ? $path : '';
    }

    private static function is_my_home(string $script, string $path): bool {
        $needles = [
            '/my/index.php',
            '/my/courses.php',
        ];
        foreach ($needles as $n) {
            if (str_ends_with($script, $n) || str_contains($path, $n)) {
                return true;
            }
        }
        // Exact /my/ without deeper resource.
        if (preg_match('#/my/?$#', $path) || preg_match('#/my/index\\.php#', $path)) {
            return true;
        }
        return false;
    }

    private static function is_calendar(string $script, string $path): bool {
        return str_contains($script, '/calendar/') || str_contains($path, '/calendar/');
    }

    private static function is_private_files(string $script, string $path): bool {
        return str_ends_with($script, '/user/files.php')
            || str_contains($path, '/user/files.php');
    }

    private static function is_moodle_grades_ui(string $script, string $path): bool {
        // Only top-level student grade reports, not course grade for teachers.
        $targets = [
            '/grade/report/overview/',
            '/grade/report/mygrades.php',
            '/grade/report/user/index.php',
        ];
        foreach ($targets as $t) {
            if (str_contains($script, $t) || str_contains($path, $t)) {
                return true;
            }
        }
        return false;
    }

    private static function is_blog(string $script, string $path): bool {
        return str_contains($script, '/blog/') || str_contains($path, '/blog/');
    }
}
