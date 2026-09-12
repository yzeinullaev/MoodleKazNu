<?php
// This file is part of Moodle - http://moodle.org/
//
// Proctopus quiz access rule. When global plugin disabled OR per-quiz off → null.
// When required, free start is blocked until local session status=admitted
// (or short PHP session window after return).
// Does not INSERT quiz_attempts. Does not trust browser name/time.

use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;
use mod_quiz_mod_form;
use MoodleQuickForm;

defined('MOODLE_INTERNAL') || die();

// Always load helper lib for mode/status panel (not auto-included on all pages).
require_once($CFG->dirroot . '/local/proctopus/lib.php');

/**
 * Quiz access rule: Proctopus.
 */
class quizaccess_proctopus extends access_rule_base {

    /**
     * @param quiz_settings $quizobj
     * @param int $timenow
     * @param bool $canignoretimelimits
     * @return self|null
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        if (!function_exists('local_proctopus_is_enabled') || !local_proctopus_is_enabled()) {
            return null;
        }
        $quiz = $quizobj->get_quiz();
        if (empty($quiz->proctopusrequired)) {
            return null;
        }
        return new self($quizobj, $timenow);
    }

    /**
     * Block free start until proctoring session is admitted.
     *
     * @return string|false
     */
    public function prevent_access() {
        global $USER, $SESSION;

        $cmid = (int) $this->quizobj->get_cmid();
        $userid = (int) $USER->id;

        // Reconcile before any admit reuse (finished attempt → no active sessions).
        if (class_exists(\local_proctopus\service\proctoring_session_reconciler::class)) {
            \local_proctopus\service\proctoring_session_reconciler::reconcile_user_cm($userid, $cmid);
        }

        if (class_exists(\local_proctopus\service\session_service::class)) {
            $svc = new \local_proctopus\service\session_service();
            if ($svc->user_has_admitted_session($userid, $cmid)) {
                return false;
            }
        }

        // PHP session admit window only if a real admitted DB session still exists after reconcile.
        if (!empty($SESSION->local_proctopus_admit[$cmid])
                && (int) $SESSION->local_proctopus_admit[$cmid] >= time()) {
            if (class_exists(\local_proctopus\service\session_service::class)) {
                $svc = new \local_proctopus\service\session_service();
                if ($svc->user_has_admitted_session($userid, $cmid)) {
                    return false;
                }
            }
            unset($SESSION->local_proctopus_admit[$cmid]);
        }

        $check = new moodle_url('/local/proctopus/check.php', ['cmid' => $cmid]);

        // startattempt/attempt throw moodle_exception which escapes {$a} — so HTML becomes
        // visible tags. Redirect to prep instead of returning markup on those entry points.
        $script = (string) ($_SERVER['SCRIPT_FILENAME'] ?? ($_SERVER['SCRIPT_NAME'] ?? ''));
        $basename = strtolower(basename(str_replace('\\', '/', $script)));
        if (in_array($basename, ['startattempt.php', 'attempt.php', 'summary.php'], true)) {
            redirect($check);
        }

        // Quiz view page renders prevent messages as HTML (not escaped).
        if (function_exists('local_proctopus_render_readiness_panel')) {
            return local_proctopus_render_readiness_panel($cmid, false);
        }

        // Plain-text fallback (safe if ever passed through s()/exception $a).
        return get_string('must_start_proctoring', 'quizaccess_proctopus', $check->out(false));
    }

    public function description() {
        $cmid = (int) $this->quizobj->get_cmid();
        $mode = function_exists('local_proctopus_ui_mode') ? local_proctopus_ui_mode() : 'disabled';
        $chip = '';
        if (function_exists('local_proctopus_render_status_chip') && $mode !== 'disabled') {
            // Landing: never “proctoring active” before admit.
            $chip = local_proctopus_render_status_chip($mode, 'prep');
        }
        $check = new moodle_url('/local/proctopus/check.php', ['cmid' => $cmid]);
        return $chip
            . get_string('proctopusruledesc', 'quizaccess_proctopus')
            . ' '
            . html_writer::link(
                $check,
                get_string('cta_open_prep', 'local_proctopus'),
                ['class' => 'btn btn-secondary btn-sm ml-1']
            );
    }

    /**
     * @param mod_quiz_mod_form $quizform
     * @param MoodleQuickForm $mform
     */
    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $mform->addElement('header', 'proctopusheader', get_string('pluginname', 'quizaccess_proctopus'));
        $mform->addElement('selectyesno', 'proctopusrequired', get_string('proctopusrequired', 'quizaccess_proctopus'));
        $mform->setDefault('proctopusrequired', 0);
        $mform->addElement('text', 'proctopuswebsites', get_string('proctopuswebsites', 'quizaccess_proctopus'), ['size' => 40]);
        $mform->setType('proctopuswebsites', PARAM_TEXT);
        $mform->addElement('selectyesno', 'proctopuslookaway', get_string('proctopuslookaway', 'quizaccess_proctopus'));
        $mform->addElement('selectyesno', 'proctopusmoveaway', get_string('proctopusmoveaway', 'quizaccess_proctopus'));
        $mform->addElement('selectyesno', 'proctopusvoices', get_string('proctopusvoices', 'quizaccess_proctopus'));
        $mform->addElement('text', 'proctopusduration', get_string('proctopusduration', 'quizaccess_proctopus'));
        $mform->setType('proctopusduration', PARAM_INT);
        $mform->addHelpButton('proctopusduration', 'proctopusduration', 'quizaccess_proctopus');
        $mform->addElement('text', 'proctopusmode', get_string('proctopusmode', 'quizaccess_proctopus'));
        $mform->setType('proctopusmode', PARAM_TEXT);
    }

    /**
     * @param stdClass $quiz
     */
    public static function save_settings($quiz) {
        global $DB;
        if (empty($quiz->id)) {
            return;
        }
        $DB->delete_records('quizaccess_proctopus', ['quizid' => $quiz->id]);
        $DB->insert_record('quizaccess_proctopus', (object) [
            'quizid' => $quiz->id,
            'proctopusrequired' => empty($quiz->proctopusrequired) ? 0 : 1,
            'proctopuswebsites' => (string) ($quiz->proctopuswebsites ?? ''),
            'proctopuslookaway' => empty($quiz->proctopuslookaway) ? 0 : 1,
            'proctopusmoveaway' => empty($quiz->proctopusmoveaway) ? 0 : 1,
            'proctopusvoices' => empty($quiz->proctopusvoices) ? 0 : 1,
            'proctopusduration' => (int) ($quiz->proctopusduration ?? 0),
            'proctopusmode' => (string) ($quiz->proctopusmode ?? ''),
        ]);
    }

    /**
     * @param stdClass $quiz
     */
    public static function delete_settings($quiz) {
        global $DB;
        $DB->delete_records('quizaccess_proctopus', ['quizid' => $quiz->id]);
    }

    /**
     * @param int $quizid
     * @return array
     */
    public static function get_settings_sql($quizid) {
        return [
            'proctopusrequired, proctopuswebsites, proctopuslookaway, proctopusmoveaway, proctopusvoices, proctopusduration, proctopusmode',
            'LEFT JOIN {quizaccess_proctopus} proctopus ON proctopus.quizid = quiz.id',
            [],
        ];
    }
}
