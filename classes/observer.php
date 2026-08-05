<?php
namespace local_kaznu;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../locallib.php');

/**
 * Quiz submit / grade → unlock modules + award hard XP.
 * User delete → purge Univer cache / XP rows.
 */
class observer {

    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        self::process_attempt($event);
    }

    public static function quiz_attempt_graded(\mod_quiz\event\attempt_graded $event): void {
        self::process_attempt($event);
    }

    /**
     * Drop plugin data when a Moodle account is deleted.
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        $userid = (int) $event->objectid;
        if ($userid <= 0) {
            return;
        }
        (new \local_kaznu\service\user_service())->purge_for_userid($userid);
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

        $userid = (int) $attempt->userid;
        $quizid = (int) $attempt->quiz;

        // Summer school: unlock next modules via completion + award XP.
        if ($course->shortname === LOCAL_KAZNU_COURSE_SHORTNAME) {
            local_kaznu_sync_user_quiz_completion($course, $userid, $quizid);
            return;
        }

        $gi = $DB->get_record('grade_items', [
            'courseid' => $course->id,
            'itemmodule' => 'quiz',
            'iteminstance' => $quizid,
        ]);
        if (!$gi) {
            return;
        }
        $gg = $DB->get_record('grade_grades', ['itemid' => $gi->id, 'userid' => $userid]);
        if (!$gg || $gg->finalgrade === null) {
            return;
        }

        local_kaznu_award_quiz_xp($userid, $quizid, (float) $gg->finalgrade, (float) ($gi->grademax ?: 100));
    }
}
