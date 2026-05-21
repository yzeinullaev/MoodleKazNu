<?php
namespace local_kaznu;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../locallib.php');

/**
 * After quiz submit — unlock next module when pass grade received.
 */
class observer {

    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        self::process_attempt($event);
    }

    public static function quiz_attempt_graded(\mod_quiz\event\attempt_graded $event): void {
        self::process_attempt($event);
    }

    /**
     * @param \mod_quiz\event\attempt_submitted|\mod_quiz\event\attempt_graded $event
     */
    private static function process_attempt($event): void {
        global $DB;

        $course = $DB->get_record('course', ['id' => $event->courseid]);
        if (!$course || $course->shortname !== LOCAL_KAZNU_COURSE_SHORTNAME) {
            return;
        }

        $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);
        if (!$attempt || $attempt->state !== 'finished') {
            return;
        }

        local_kaznu_sync_user_quiz_completion($course, (int) $attempt->userid, (int) $attempt->quiz);
    }
}
