<?php
// This file is part of Moodle - http://moodle.org/

namespace local_proctopus;

defined('MOODLE_INTERNAL') || die();

/**
 * Quiz attempt observers for Proctopus finish/start mapping.
 */
class observer {

    /**
     * attempt_started: bind admitted session to attempt id.
     *
     * @param \mod_quiz\event\attempt_started $event
     */
    public static function attempt_started(\mod_quiz\event\attempt_started $event): void {
        global $DB;

        $attemptid = (int)$event->objectid;
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], 'id,quiz,userid', IGNORE_MISSING);
        if (!$attempt) {
            return;
        }
        $cm = get_coursemodule_from_instance('quiz', $attempt->quiz, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }
        if (!\local_proctopus_is_enabled() || !\local_proctopus_live_cmid_allowed((int)$cm->id)) {
            // Still reconcile mapping when plugin on; start link is local-only.
            if (!\local_proctopus_is_enabled()) {
                return;
            }
        }

        try {
            \local_proctopus\service\proctoring_session_reconciler::on_attempt_started(
                (int)$attempt->userid,
                (int)$attempt->quiz,
                (int)$cm->id,
                $attemptid
            );
        } catch (\Throwable $e) {
            debugging('local_proctopus attempt_started: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * attempt_submitted: Moodle moved attempt to finished (source of truth).
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    public static function attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        self::finish_from_attempt_id((int)$event->objectid);
    }

    /**
     * Fallback if overdue closes attempt similarly.
     *
     * @param \mod_quiz\event\attempt_becameoverdue $event
     */
    public static function attempt_becameoverdue(\mod_quiz\event\attempt_becameoverdue $event): void {
        global $DB;
        $attemptid = (int)$event->objectid;
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], 'id,state', IGNORE_MISSING);
        if ($attempt && $attempt->state === 'finished') {
            self::finish_from_attempt_id($attemptid);
        }
    }

    /**
     * Shared finish handler.
     */
    private static function finish_from_attempt_id(int $attemptid): void {
        global $DB;

        if ($attemptid <= 0) {
            return;
        }
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', IGNORE_MISSING);
        if (!$attempt) {
            return;
        }
        // Only act when Moodle says finished.
        if ($attempt->state !== 'finished') {
            return;
        }
        $cm = get_coursemodule_from_instance('quiz', $attempt->quiz, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }
        if (!\local_proctopus_is_enabled()) {
            return;
        }

        try {
            \local_proctopus\service\proctoring_session_reconciler::on_attempt_finished(
                (int)$attempt->userid,
                (int)$attempt->quiz,
                (int)$cm->id,
                $attemptid
            );
        } catch (\Throwable $e) {
            debugging('local_proctopus attempt finish: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
