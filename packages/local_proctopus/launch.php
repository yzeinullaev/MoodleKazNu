<?php
/**
 * Launch Proctopus after preparation. Prefer POST from check.php.
 * GET redirects to preparation page (never starts provider from bare URL alone).
 */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_proctopus\service\session_service;

require_login();

$cmid = required_param('cmid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$prepok = optional_param('prep_ok', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$context = context_module::instance($cm->id);
require_capability('mod/quiz:attempt', $context);

$PAGE->set_url('/local/proctopus/launch.php', ['cmid' => $cmid]);
$PAGE->set_context($context);
$PAGE->set_cm($cm);
$PAGE->set_course($course);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('step_proctoring', 'local_proctopus'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('local-proctopus-launch');
$PAGE->add_body_class('local-proctopus-hide-achievements');

if (!local_proctopus_is_enabled()) {
    throw new moodle_exception('proctopus_disabled', 'local_proctopus');
}

// Always land on preparation first for non-POST or unconfirmed requests.
if (!$confirm || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect(new moodle_url('/local/proctopus/check.php', ['cmid' => $cmid]));
}

require_sesskey();

// Soft gate: prefer coming from prep (JS sets prep_ok). Still allow POST with sesskey.
if (!$prepok) {
    // Reopen prep if someone POSTs without client readiness flag (except already admitted).
    $admitted = false;
    if (class_exists(session_service::class)) {
        $admitted = (new session_service())->user_has_admitted_session((int) $USER->id, $cmid);
    }
    if (!$admitted) {
        redirect(new moodle_url('/local/proctopus/check.php', ['cmid' => $cmid]));
    }
}

try {
    $svc = new session_service();
    $out = $svc->launch_for_cm((int) $USER->id, $cmid);
    if (!empty($out['already_admitted'])) {
        redirect(new moodle_url('/mod/quiz/view.php', ['id' => $cmid]));
    }
    $redir = $out['redirect_url'];
    redirect($redir);
} catch (moodle_exception $e) {
    redirect(new moodle_url('/local/proctopus/check.php', [
        'cmid' => $cmid,
        'error' => 'provider',
    ]));
}
