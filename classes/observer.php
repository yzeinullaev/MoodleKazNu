<?php
namespace local_kaznu;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../locallib.php');

/**
 * Quiz submit / grade → unlock modules + award hard XP.
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
        if (!$course || (int) $course->id <= 1) {
            return;
        }

        $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);
        if (!$attempt || $attempt->state !== 'finished') {
            return;
        }

        // Unlock path for summer school; XP for any catalogue course.
        if ($course->shortname === LOCAL_KAZNU_COURSE_SHORTNAME) {
            local_kaznu_sync_user_quiz_completion($course, (int) $attempt->userid, (int) $attempt->quiz);
            return;
        }

        $gi = $DB->get_record('grade_items', [
            'courseid' => $course->id,
            'itemmodule' => 'quiz',
            'iteminstance' => $attempt->quiz,
        ]);
        if (!$gi) {
            return;
        }
        $gg = $DB->get_record('grade_grades', ['itemid' => $gi->id, 'userid' => $attempt->userid]);
        if (!$gg || $gg->finalgrade === null || (float) $gg->finalgrade < 60) {
            return;
        }

        $xp = LOCAL_KAZNU_XP_QUIZ_PASS;
        $badge = 'quiz_pass';
        if ((float) $gg->finalgrade >= 100) {
            $xp += LOCAL_KAZNU_XP_QUIZ_PERFECT;
            $badge = 'perfect_score';
        }
        local_kaznu_award_xp((int) $attempt->userid, $xp, 'quiz_pass', $badge);
    }
}
