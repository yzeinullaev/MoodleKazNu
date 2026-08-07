<?php
namespace local_proctopus\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Bridges to Moodle Quiz attempt start after proctoring admission.
 * Never inserts quiz_attempts rows directly.
 */
class attempt_bridge {
    /**
     * After session status=admitted, send the user into the standard Moodle startattempt flow.
     * Moodle itself creates the attempt with correct layout/uniqueid.
     */
    public static function redirect_to_standard_start(int $cmid, int $userid): void {
        global $USER, $CFG, $SESSION;

        if ((int) $USER->id !== $userid) {
            throw new \moodle_exception('nopermissions', 'error', '', 'start attempt');
        }

        $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance((int) $cm->id);
        require_capability('mod/quiz:attempt', $context);

        // Session marker so quizaccess_proctopus prevent_access allows through for short window.
        if (!isset($SESSION->local_proctopus_admit) || !is_array($SESSION->local_proctopus_admit)) {
            $SESSION->local_proctopus_admit = [];
        }
        $SESSION->local_proctopus_admit[$cmid] = time() + 300;

        // Prefer standard startattempt.php — never craft attempt records.
        $url = new \moodle_url('/mod/quiz/startattempt.php', [
            'cmid' => $cmid,
            'sesskey' => sesskey(),
        ]);
        $out = $url->out(false);
        if (str_contains($out, 'dl.kaznu') || str_contains($out, 'kaznu_moodle')) {
            throw new \moodle_exception('proctopus_not_configured', 'local_proctopus');
        }
        redirect($url);
    }
}
