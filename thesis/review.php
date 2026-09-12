<?php
/**
 * Supervisor review + EDS stub sign.
 *
 * @package local_kaznu
 */

require_once(__DIR__ . '/../../../config.php');

use local_kaznu\service\thesis_service;

require_login();
if (isguestuser()) {
    redirect(new moodle_url('/login/index.php'));
}

$sys = context_system::instance();
$id = required_param('id', PARAM_INT);
$PAGE->set_url(new moodle_url('/local/kaznu/thesis/review.php', ['id' => $id]));
$PAGE->set_context($sys);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('thesis_review_action', 'local_kaznu'));
$PAGE->set_heading(get_string('thesis_review_action', 'local_kaznu'));
$PAGE->requires->css(new moodle_url('/local/kaznu/styles.css'));

thesis_service::require_enabled();
$sub = thesis_service::get($id);
if (!$sub) {
    throw new moodle_exception('thesis_error_forbidden', 'local_kaznu');
}
$ismanage = has_capability('local/kaznu:thesismanage', $sys) || is_siteadmin();
$issupervisor = has_capability('local/kaznu:thesissupervise', $sys) && (int) $sub->supervisorid === (int) $USER->id;
if (!$issupervisor && !$ismanage) {
    throw new moodle_exception('thesis_error_forbidden', 'local_kaznu');
}

if (data_submitted() && confirm_sesskey()) {
    $decision = required_param('decision', PARAM_ALPHANUMEXT);
    $comment = optional_param('comment', '', PARAM_TEXT);
    thesis_service::supervisor_decide($id, (int) $USER->id, $decision, $comment, $ismanage);
    redirect(new moodle_url('/local/kaznu/thesis/report.php', ['id' => $id]), get_string('thesis_decide_ok', 'local_kaznu'));
}

$student = core_user::get_user((int) $sub->userid);
$report = thesis_service::decode_report($sub->reportjson);

echo $OUTPUT->header();
echo html_writer::start_div('kz-thesis');
echo html_writer::link(new moodle_url('/local/kaznu/thesis/queue.php'), get_string('thesis_nav_queue', 'local_kaznu'), ['class' => 'kz-thesis-link']);
echo html_writer::tag('h2', format_string($sub->title), ['class' => 'kz-thesis-h']);
echo html_writer::tag('p', get_string('thesis_col_student', 'local_kaznu') . ': ' . ($student ? fullname($student) : $sub->userid), ['class' => 'kz-thesis-lead']);

echo html_writer::start_div('kz-thesis-pillars');
echo html_writer::div(
    html_writer::div('01', 'kz-thesis-pillar-n') .
    html_writer::tag('h3', get_string('thesis_pillar_ai', 'local_kaznu')) .
    html_writer::tag('p', 'Score: ' . (int) ($report['ai']['score'] ?? 0) . '%'),
    'kz-thesis-pillar'
);
echo html_writer::div(
    html_writer::div('02', 'kz-thesis-pillar-n') .
    html_writer::tag('h3', get_string('thesis_pillar_plag', 'local_kaznu')) .
    html_writer::tag('p', (int) ($report['plagiarism']['originality'] ?? 0) . '%'),
    'kz-thesis-pillar'
);
echo html_writer::div(
    html_writer::div('03', 'kz-thesis-pillar-n') .
    html_writer::tag('h3', get_string('thesis_pillar_eds', 'local_kaznu')) .
    html_writer::tag('p', get_string('thesis_eds_stub_note', 'local_kaznu')),
    'kz-thesis-pillar'
);
echo html_writer::end_div();

echo html_writer::link(new moodle_url('/local/kaznu/thesis/report.php', ['id' => $id]), get_string('thesis_report_title', 'local_kaznu'), ['class' => 'kz-thesis-link-strong']);

if ($sub->status === 'submitted') {
    echo html_writer::start_tag('form', [
        'method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'kz-thesis-form', 'id' => 'kz-eds-form',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::tag('label', get_string('thesis_supervisor_comment', 'local_kaznu'), ['class' => 'kz-thesis-label']);
    echo html_writer::tag('textarea', s((string) $sub->supervisorcomment), [
        'name' => 'comment', 'class' => 'kz-thesis-input', 'rows' => 4,
    ]);
    echo html_writer::start_div('kz-thesis-actions');
    echo html_writer::tag('button', get_string('thesis_decide_approve', 'local_kaznu'), [
        'type' => 'submit', 'name' => 'decision', 'value' => 'approve', 'class' => 'kz-thesis-btn kz-thesis-btn-primary', 'id' => 'kz-eds-approve',
    ]);
    echo html_writer::tag('button', get_string('thesis_decide_revision', 'local_kaznu'), [
        'type' => 'submit', 'name' => 'decision', 'value' => 'revision', 'class' => 'kz-thesis-btn kz-thesis-btn-ghost',
    ]);
    echo html_writer::tag('button', get_string('thesis_decide_reject', 'local_kaznu'), [
        'type' => 'submit', 'name' => 'decision', 'value' => 'reject', 'class' => 'kz-thesis-btn kz-thesis-btn-danger',
    ]);
    echo html_writer::end_div();
    echo html_writer::end_tag('form');
    echo html_writer::div(
        html_writer::div('', 'kz-thesis-spinner') . html_writer::span(get_string('thesis_loading_eds', 'local_kaznu')),
        'kz-thesis-loader', ['id' => 'kz-loader-eds', 'hidden' => 'hidden']
    );
    echo html_writer::script("
    document.getElementById('kz-eds-form').addEventListener('submit', function (e) {
      var btn = e.submitter;
      if (btn && btn.value === 'approve') {
        var l = document.getElementById('kz-loader-eds');
        if (l) l.hidden = false;
      }
    });
    ");
} else {
    echo html_writer::div(get_string('thesis_review_not_pending', 'local_kaznu'), 'kz-thesis-alert kz-thesis-alert-info');
}

echo html_writer::end_div();
echo $OUTPUT->footer();
