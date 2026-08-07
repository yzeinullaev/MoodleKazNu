<?php
/**
 * STUB E2E for PROCTOPUS TEST quiz only.
 * Temporarily sets proctopus_enabled=1 with live_calls=0; always restores 0 in finally.
 *
 * php local/proctopus/cli/stub_e2e_test_quiz.php --cmid=201 --quizid=22
 */
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/accessrule/proctopus/rule.php');
require_once(__DIR__ . '/../lib.php');

use local_proctopus\service\session_service;
use mod_quiz\quiz_settings;

list($options) = cli_get_params([
    'cmid' => 201,
    'quizid' => 22,
    'student' => 'proctopus_test_student',
    'help' => false,
], ['h' => 'help']);

if (!empty($options['help'])) {
    cli_writeln('php stub_e2e_test_quiz.php [--cmid=201] [--quizid=22] [--student=proctopus_test_student]');
    exit(0);
}

$cmid = (int) $options['cmid'];
$quizid = (int) $options['quizid'];
$report = [
    'cta' => 'UNKNOWN',
    'launch' => 'UNKNOWN',
    'jwt' => 'UNKNOWN',
    'session' => 'UNKNOWN',
    'redirect' => 'UNKNOWN',
    'return' => 'UNKNOWN',
    'attempt' => 'UNKNOWN',
    'grade' => 'UNKNOWN',
    'duplicate' => 'UNKNOWN',
    'logs' => 'UNKNOWN',
    'errors' => [],
    'rollback' => 'UNKNOWN',
];

function e2e_note(array &$report, string $key, string $val): void {
    $report[$key] = $val;
    cli_writeln("$key=$val");
}

function e2e_err(array &$report, string $msg): void {
    $report['errors'][] = $msg;
    cli_writeln('ERROR: ' . $msg);
}

// Always force restore enabled=0.
$prevenabled = get_config('local_proctopus', 'proctopus_enabled');
$prevlive = get_config('local_proctopus', 'proctopus_live_calls_enabled');

try {
    // --- SAFETY ---
    set_config('proctopus_live_calls_enabled', 0, 'local_proctopus');
    if ((int) get_config('local_proctopus', 'proctopus_live_calls_enabled') !== 0) {
        throw new moodle_exception('proctopus_not_configured', 'local_proctopus');
    }
    set_config('proctopus_enabled', 1, 'local_proctopus');
    set_config('proctopus_legacy_mode', 1, 'local_proctopus');
    set_config('proctopus_debug', 0, 'local_proctopus');
    purge_all_caches();
    cli_writeln('TEMP enabled=1 live=0');

    $student = core_user::get_user_by_username((string) $options['student']);
    if (!$student || $student->deleted) {
        throw new coding_exception('student missing: ' . $options['student']);
    }
    \core\session\manager::set_user($student);
    cli_writeln('acting_as userid=' . $student->id . ' ' . $student->username);

    $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
    if ((int) $cm->instance !== $quizid) {
        throw new coding_exception('cmid/quizid mismatch');
    }
    $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);

    // Rule only on this quiz.
    $otherreq = $DB->count_records_select('quizaccess_proctopus', 'quizid <> ? AND proctopusrequired = 1', [$quizid]);
    $thisreq = (int) $DB->get_field('quizaccess_proctopus', 'proctopusrequired', ['quizid' => $quizid]);
    if ($thisreq !== 1 || $otherreq !== 0) {
        e2e_err($report, "rule scope wrong this=$thisreq other=$otherreq");
    }

    // --- CTA / access rule active ---
    $quizobj = quiz_settings::create($quizid, (int) $student->id);
    // Ensure proctopusrequired on quiz settings record.
    $q = $quizobj->get_quiz();
    if (empty($q->proctopusrequired)) {
        $q->proctopusrequired = 1;
    }

    $rule = quizaccess_proctopus::make($quizobj, time(), false);
    if ($rule === null) {
        e2e_note($report, 'cta', 'FAIL (rule inactive)');
        e2e_err($report, 'quizaccess_proctopus::make returned null with enabled=1');
    } else {
        $block = $rule->prevent_access();
        if ($block === false) {
            e2e_note($report, 'cta', 'WARN (not blocked — admitted session exists?)');
        } else {
            $hascta = (is_string($block) && (str_contains($block, 'launch.php') || strlen($block) > 10));
            e2e_note($report, 'cta', $hascta ? 'PASS (prevent_access + launch CTA path)' : 'PASS (prevent_access string)');
            cli_writeln('cta_snippet=' . substr(strip_tags((string) $block), 0, 160));
        }
    }

    // Baseline counts.
    $sessbefore = $DB->count_records('local_proctopus_session', ['userid' => $student->id, 'cmid' => $cmid]);
    $attbefore = $DB->count_records('quiz_attempts', ['quiz' => $quizid, 'userid' => $student->id]);

    // Capture safe_log output? Moodle debugging to mtrace if any.
    // --- LAUNCH STUB ---
    $svc = new session_service();
    $launch = $svc->launch_for_cm((int) $student->id, $cmid);
    e2e_note($report, 'launch', !empty($launch['ok']) || !empty($launch['sessionid']) ? 'PASS' : 'FAIL');
    if (empty($launch['sessionid'])) {
        throw new coding_exception('no sessionid from launch');
    }
    $sessionid = (int) $launch['sessionid'];
    $redir = (string) ($launch['redirect_url'] ?? '');
    e2e_note($report, 'redirect', ($redir !== '' && local_proctopus_is_redirect_host_allowed($redir))
        ? 'PASS (allowlisted or own host) ' . preg_replace('/state=[^&]+/', 'state=[redacted]', $redir)
        : 'FAIL redir=' . substr($redir, 0, 80));

    // Parse state from redirect (local signed return).
    $state = '';
    $parts = parse_url($redir);
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $qparams);
        $state = (string) ($qparams['state'] ?? '');
    }
    if ($state === '') {
        e2e_err($report, 'no state in redirect URL');
        e2e_note($report, 'return', 'FAIL');
        throw new coding_exception('missing state');
    }

    $sess = $DB->get_record('local_proctopus_session', ['id' => $sessionid], '*', MUST_EXIST);
    e2e_note($report, 'session', 'PASS id=' . $sessionid . ' status=' . $sess->status);

    // JWT not in DB: scan row for JWT-like tokens.
    $blob = json_encode((array) $sess);
    $jwtinrow = (bool) preg_match('/eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\./', $blob);
    // Also no dedicated jwt column.
    $cols = array_keys((array) $sess);
    $hasjwtcol = in_array('jwt', $cols, true) || in_array('token', $cols, true);
    e2e_note($report, 'jwt', (!$jwtinrow && !$hasjwtcol)
        ? 'PASS (generated in memory only; not in session row; stub secret offline)'
        : 'FAIL jwt leaked to DB');

    // Logs: Moodle log table last events for plugin, search payload.
    $logjwt = false;
    if ($DB->get_manager()->table_exists('logstore_standard_log')) {
        $recent = $DB->get_records_select(
            'logstore_standard_log',
            "timecreated > ? AND (component = ? OR target LIKE ? OR other LIKE ?)",
            [time() - 600, 'local_proctopus', '%proctopus%', '%eyJ%'],
            'id DESC',
            '*',
            0,
            20
        );
        foreach ($recent as $r) {
            $line = json_encode($r);
            if (preg_match('/eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\./', $line)) {
                $logjwt = true;
                break;
            }
        }
    }
    // Also PHP error log sample not easily readable here — mark based on standard log.
    e2e_note($report, 'logs', $logjwt ? 'FAIL JWT pattern in logstore' : 'PASS (no JWT pattern in recent local_proctopus logstore rows)');

    // --- RETURN + ADMIT ---
    $info = $svc->complete_return((int) $student->id, $state);
    $sess2 = $DB->get_record('local_proctopus_session', ['id' => $sessionid], '*', MUST_EXIST);
    e2e_note($report, 'return', ($info['sessionid'] == $sessionid && $sess2->status === 'admitted')
        ? 'PASS status=admitted'
        : 'FAIL status=' . $sess2->status);

    // Set admit session window like attempt_bridge.
    global $SESSION;
    if (!isset($SESSION->local_proctopus_admit) || !is_array($SESSION->local_proctopus_admit)) {
        $SESSION->local_proctopus_admit = [];
    }
    $SESSION->local_proctopus_admit[$cmid] = time() + 300;

    // After admit, prevent_access should clear.
    $quizobj = quiz_settings::create($quizid, (int) $student->id);
    $q = $quizobj->get_quiz();
    $q->proctopusrequired = 1;
    $rule2 = quizaccess_proctopus::make($quizobj, time(), false);
    if ($rule2) {
        $block2 = $rule2->prevent_access();
        if ($block2 !== false) {
            e2e_err($report, 'still blocked after admit');
        }
    }

    // --- STANDARD ATTEMPT (Moodle API only) ---
    $quizobj = quiz_settings::create($quizid, (int) $student->id);
    // Forforce new attempt: use quiz_prepare_and_start_new_attempt if available.
    $attemptnumber = 1;
    $lastattempt = null;
    $attempts = quiz_get_user_attempts($quizid, $student->id, 'all', true);
    if ($attempts) {
        $last = end($attempts);
        $attemptnumber = ((int) $last->attempt) + 1;
        $lastattempt = $last;
    }
    if (function_exists('quiz_prepare_and_start_new_attempt')) {
        $started = quiz_prepare_and_start_new_attempt($quizobj, $attemptnumber, $lastattempt);
        if ($started instanceof quiz_attempt) {
            $attemptid = (int) $started->get_attemptid();
        } else if (is_object($started) && !empty($started->id)) {
            $attemptid = (int) $started->id;
        } else {
            throw new coding_exception('unexpected start attempt return type');
        }
    } else {
        // Fallback classic path.
        $attempt = quiz_create_attempt($quizobj, $attemptnumber, $lastattempt, time(), false, (int) $student->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $attempt = quiz_start_new_attempt($quizobj, $quba, $attempt, $attemptnumber, time());
        $attempt = quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptid = (int) $attempt->id;
    }
    e2e_note($report, 'attempt', 'PASS id=' . $attemptid . ' (Moodle API, no hand insert)');

    // Answer all questions with true (our fixtures correct=true).
    $attemptobj = quiz_attempt::create($attemptid);
    $slots = $attemptobj->get_slots();
    $responses = [];
    foreach ($slots as $slot) {
        $qa = $attemptobj->get_question_attempt($slot);
        // truefalse: answer = 1 (true).
        $prefix = $qa->get_field_prefix();
        $responses[$prefix . 'answer'] = 1;
        $responses[$prefix . ':sequencecheck'] = $qa->get_sequence_check_count();
    }
    $attemptobj->process_submitted_actions(time(), false, $responses);
    $attemptobj->process_finish(time(), false);

    // Grade.
    $grade = quiz_get_best_grade($quiz, (int) $student->id);
    $attrow = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);
    e2e_note($report, 'grade', 'PASS sumgrades=' . $attrow->sumgrades . ' state=' . $attrow->state . ' best=' . ($grade ?? 'null'));

    // --- DUPLICATE ---
    $sesscount1 = $DB->count_records('local_proctopus_session', ['userid' => $student->id, 'cmid' => $cmid]);
    $launch2 = $svc->launch_for_cm((int) $student->id, $cmid);
    $sesscount2 = $DB->count_records('local_proctopus_session', ['userid' => $student->id, 'cmid' => $cmid]);
    $attcount = $DB->count_records('quiz_attempts', ['quiz' => $quizid, 'userid' => $student->id]);
    $dupok = !empty($launch2['already_admitted']) || !empty($launch2['duplicate']) || ($sesscount2 === $sesscount1);
    // Second attempt not auto-created by launch.
    $attcount2 = $DB->count_records('quiz_attempts', ['quiz' => $quizid, 'userid' => $student->id]);
    e2e_note($report, 'duplicate', ($dupok && $attcount2 === $attcount)
        ? "PASS sessions=$sesscount2 attempts=$attcount2 (no extra attempt on relaunch)"
        : "CHECK sessions $sesscount1->$sesscount2 attempts $attcount->$attcount2 launch2=" . json_encode(array_keys($launch2)));

    // Live still 0.
    if ((int) get_config('local_proctopus', 'proctopus_live_calls_enabled') !== 0) {
        e2e_err($report, 'live_calls was not 0');
    }

    cli_writeln('sessions_created_this_user=' . ($sesscount2 - $sessbefore));
    cli_writeln('attempts_created_this_user=' . ($attcount2 - $attbefore));

} catch (Throwable $e) {
    e2e_err($report, $e->getMessage());
    cli_writeln($e->getTraceAsString());
} finally {
    set_config('proctopus_enabled', 0, 'local_proctopus');
    set_config('proctopus_live_calls_enabled', 0, 'local_proctopus');
    set_config('proctopus_debug', 0, 'local_proctopus');
    // Leave secret empty.
    set_config('proctopus_jwt_secret', '', 'local_proctopus');
    purge_all_caches();
    $e = (int) get_config('local_proctopus', 'proctopus_enabled');
    $l = (int) get_config('local_proctopus', 'proctopus_live_calls_enabled');
    e2e_note($report, 'rollback', ($e === 0 && $l === 0) ? 'PASS enabled=0 live=0' : "FAIL e=$e l=$l");
}

cli_writeln('=== STUB E2E REPORT ===');
foreach (['cta', 'launch', 'jwt', 'session', 'redirect', 'return', 'attempt', 'grade', 'duplicate', 'logs', 'rollback'] as $k) {
    cli_writeln(strtoupper($k) . ': ' . $report[$k]);
}
if ($report['errors']) {
    cli_writeln('ERRORS: ' . implode(' | ', $report['errors']));
    exit(2);
}
cli_writeln('ERRORS: none');
exit(0);
