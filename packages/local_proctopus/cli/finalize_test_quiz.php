<?php
/**
 * Attach quizaccess_proctopus only to given quiz; verify no other quizzes.
 * Also force site flags OFF and print report lines.
 *
 * php local/proctopus/cli/finalize_test_quiz.php --quizid=22 --cmid=201 --courseid=24
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once(__DIR__ . '/../lib.php');

list($options) = cli_get_params([
    'quizid' => 0,
    'cmid' => 0,
    'courseid' => 0,
], []);
$quizid = (int) $options['quizid'];
$cmid = (int) $options['cmid'];
$courseid = (int) $options['courseid'];
if (!$quizid) {
    cli_error('need --quizid');
}

if ($DB->get_manager()->table_exists('quizaccess_proctopus')) {
    $DB->delete_records('quizaccess_proctopus', ['quizid' => $quizid]);
    $DB->insert_record('quizaccess_proctopus', (object) [
        'quizid' => $quizid,
        'proctopusrequired' => 1,
        'proctopuswebsites' => '',
        'proctopuslookaway' => 0,
        'proctopusmoveaway' => 0,
        'proctopusvoices' => 0,
        'proctopusduration' => 0,
        'proctopusmode' => 'online',
    ]);
}

set_config('proctopus_enabled', 0, 'local_proctopus');
set_config('proctopus_live_calls_enabled', 0, 'local_proctopus');

$other = $DB->count_records_select('quizaccess_proctopus', 'quizid <> ? AND proctopusrequired = 1', [$quizid]);
$slots = $DB->count_records('quiz_slots', ['quizid' => $quizid]);
$attempts = $DB->count_records('quiz_attempts', ['quiz' => $quizid]);
$sessions = $DB->get_manager()->table_exists('local_proctopus_session')
    ? $DB->count_records('local_proctopus_session', ['quizid' => $quizid]) : 0;

// Offline JWT smoke (does not need site enable for pure class encode).
require_once($CFG->dirroot . '/local/proctopus/lib.php');
use local_proctopus\jwt\hs_token_service;
use local_proctopus\jwt\legacy_payload_builder;
use local_proctopus\local\state_token;

$jwtok = false;
$payloadok = false;
$stateok = false;
try {
    $jwt = new hs_token_service();
    $claims = $jwt->build_claims('s1', 'farabi_business', 300, ['iss' => 'farabischool.kz']);
    $token = $jwt->encode($claims, 'offline-fixture-not-prod', 'HS256');
    $jwtok = (substr_count($token, '.') === 2) && ($claims['accountName'] === 'farabi_business');

    $user = $DB->get_record('user', ['id' => 2]);
    $quiz = $DB->get_record('quiz', ['id' => $quizid]);
    $cm = (object) ['id' => $cmid, 'course' => $courseid, 'instance' => $quizid];
    $payload = (new legacy_payload_builder())->build($user, $quiz, $cm, 'ext', 'statex');
    $payloadok = ($payload['accountName'] === 'farabi_business')
        && !str_contains(json_encode($payload), 'dl.kaznu')
        && !str_contains(json_encode($payload), 'kaznu_moodle');

    $st = state_token::issue(['sid' => 1, 'uid' => 2, 'cmid' => $cmid, 'ext' => 'e', 'exp' => time() + 60]);
    $stateok = state_token::verify($st) !== null;
} catch (Throwable $e) {
    cli_writeln('offline_check_err=' . $e->getMessage());
}

cli_writeln('=== FINALIZE ===');
cli_writeln('course_id=' . $courseid);
cli_writeln('quiz_id=' . $quizid);
cli_writeln('cmid=' . $cmid);
cli_writeln('rule_attached=1');
cli_writeln('other_required_quizzes=' . $other);
cli_writeln('proctopus_enabled=' . (int) get_config('local_proctopus', 'proctopus_enabled'));
cli_writeln('live_calls=' . (int) get_config('local_proctopus', 'proctopus_live_calls_enabled'));
cli_writeln('slots=' . $slots);
cli_writeln('attempts=' . $attempts);
cli_writeln('sessions=' . $sessions);
cli_writeln('offline_jwt_ok=' . (int) $jwtok);
cli_writeln('offline_payload_ok=' . (int) $payloadok);
cli_writeln('offline_state_ok=' . (int) $stateok);
cli_writeln('quiz_view=' . (new moodle_url('/mod/quiz/view.php', ['id' => $cmid]))->out(false));
