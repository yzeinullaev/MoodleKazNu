<?php
/**
 * Upload / create thesis work.
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
$id = optional_param('id', 0, PARAM_INT);
$PAGE->set_url(new moodle_url('/local/kaznu/thesis/edit.php', $id ? ['id' => $id] : []));
$PAGE->set_context($sys);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('thesis_new', 'local_kaznu'));
$PAGE->set_heading(get_string('thesis_new', 'local_kaznu'));
$PAGE->requires->css(new moodle_url('/local/kaznu/styles.css'));

thesis_service::require_enabled();
if (!local_kaznu_thesis_can_upload()) {
    throw new moodle_exception('thesis_error_forbidden', 'local_kaznu');
}

$sub = $id ? thesis_service::get($id) : null;
if ($id && (!$sub || (int) $sub->userid !== (int) $USER->id)) {
    throw new moodle_exception('thesis_error_forbidden', 'local_kaznu');
}

$supervisors = thesis_service::supervisor_options();
$error = '';

if (data_submitted() && confirm_sesskey()) {
    $title = trim((string) required_param('title', PARAM_TEXT));
    $supervisorid = (int) optional_param('supervisorid', 0, PARAM_INT);
    $worktype = (string) optional_param('worktype', 'master', PARAM_ALPHANUMEXT);
    if (!in_array($worktype, ['bachelor', 'master', 'phd_draft'], true)) {
        $worktype = 'master';
    }
    try {
        if ($sub) {
            if (!empty($_FILES['thesisfile']['tmp_name'])) {
                $sub = thesis_service::replace_file((int) $sub->id, (int) $USER->id, $_FILES['thesisfile']);
            }
            if ($supervisorid > 0) {
                global $DB;
                $sub->supervisorid = $supervisorid;
                $sub->title = core_text::substr($title, 0, 500);
                $sub->timemodified = time();
                $DB->update_record('local_kaznu_thesis_sub', $sub);
            }
            redirect(new moodle_url('/local/kaznu/thesis/report.php', ['id' => $sub->id]));
        }
        $sub = thesis_service::create_from_upload((int) $USER->id, $title, $supervisorid, $worktype, $_FILES['thesisfile'] ?? []);
        redirect(new moodle_url('/local/kaznu/thesis/report.php', ['id' => $sub->id, 'autoload' => 1]));
    } catch (Throwable $e) {
        $error = ($e instanceof moodle_exception)
            ? get_string($e->errorcode, $e->module ?: 'local_kaznu', $e->a)
            : $e->getMessage();
    }
}

echo $OUTPUT->header();
echo html_writer::start_div('kz-thesis');
echo html_writer::link(new moodle_url('/local/kaznu/thesis/index.php'), get_string('thesis_back_list', 'local_kaznu'), ['class' => 'kz-thesis-link']);
echo html_writer::tag('h2', get_string('thesis_new', 'local_kaznu'), ['class' => 'kz-thesis-h']);
echo html_writer::tag('p', get_string('thesis_upload_lead', 'local_kaznu'), ['class' => 'kz-thesis-lead']);

if ($error !== '') {
    echo html_writer::div(s($error), 'kz-thesis-alert kz-thesis-alert-warn');
}

echo html_writer::start_tag('form', [
    'method' => 'post',
    'enctype' => 'multipart/form-data',
    'action' => $PAGE->url->out(false),
    'class' => 'kz-thesis-form',
    'id' => 'kz-thesis-upload-form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::tag('label', get_string('thesis_field_title', 'local_kaznu'), ['class' => 'kz-thesis-label']);
echo html_writer::empty_tag('input', [
    'type' => 'text', 'name' => 'title', 'class' => 'kz-thesis-input', 'required' => 'required',
    'value' => $sub ? s($sub->title) : '', 'maxlength' => 500,
]);

echo html_writer::tag('label', get_string('thesis_field_worktype', 'local_kaznu'), ['class' => 'kz-thesis-label']);
echo html_writer::select([
    'bachelor' => get_string('thesis_worktype_bachelor', 'local_kaznu'),
    'master' => get_string('thesis_worktype_master', 'local_kaznu'),
    'phd_draft' => get_string('thesis_worktype_phd_draft', 'local_kaznu'),
], 'worktype', $sub->worktype ?? 'master', false, ['class' => 'kz-thesis-input']);

echo html_writer::tag('label', get_string('thesis_field_supervisor', 'local_kaznu'), ['class' => 'kz-thesis-label']);
echo html_writer::select($supervisors, 'supervisorid', $sub->supervisorid ?? 0, false, ['class' => 'kz-thesis-input']);

echo html_writer::tag('label', get_string('thesis_field_file', 'local_kaznu'), ['class' => 'kz-thesis-label']);
$fileattrs = [
    'type' => 'file', 'name' => 'thesisfile', 'id' => 'thesisfile', 'class' => 'kz-thesis-file',
    'accept' => '.docx,.pdf,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
if (!$sub) {
    $fileattrs['required'] = 'required';
}
echo html_writer::empty_tag('input', $fileattrs);
echo html_writer::tag('p', get_string('thesis_field_file_help', 'local_kaznu'), ['class' => 'kz-thesis-help']);

echo html_writer::tag('button', get_string('thesis_save', 'local_kaznu'), [
    'type' => 'submit', 'class' => 'kz-thesis-btn kz-thesis-btn-primary', 'id' => 'kz-thesis-submit',
]);
echo html_writer::end_tag('form');

echo html_writer::div(
    html_writer::div('', 'kz-thesis-spinner') . html_writer::span(get_string('thesis_loading_upload', 'local_kaznu')),
    'kz-thesis-loader',
    ['id' => 'kz-thesis-upload-loader', 'hidden' => 'hidden']
);

echo html_writer::script("
document.getElementById('kz-thesis-upload-form').addEventListener('submit', function () {
  var l = document.getElementById('kz-thesis-upload-loader');
  var b = document.getElementById('kz-thesis-submit');
  if (l) { l.hidden = false; }
  if (b) { b.disabled = true; }
});
");

echo html_writer::end_div();
echo $OUTPUT->footer();
