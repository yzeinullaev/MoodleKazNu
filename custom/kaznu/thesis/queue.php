<?php
/**
 * Supervisor queue.
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
$PAGE->set_url(new moodle_url('/local/kaznu/thesis/queue.php'));
$PAGE->set_context($sys);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('thesis_nav_queue', 'local_kaznu'));
$PAGE->set_heading(get_string('thesis_nav_queue', 'local_kaznu'));
$PAGE->requires->css(new moodle_url('/local/kaznu/styles.css'));

thesis_service::require_enabled();
$ismanage = has_capability('local/kaznu:thesismanage', $sys) || is_siteadmin();
if (!has_capability('local/kaznu:thesissupervise', $sys) && !$ismanage) {
    throw new moodle_exception('thesis_error_forbidden', 'local_kaznu');
}

$rows = thesis_service::list_for_supervisor((int) $USER->id, $ismanage);

echo $OUTPUT->header();
echo html_writer::start_div('kz-thesis');
echo html_writer::link(new moodle_url('/local/kaznu/thesis/index.php'), get_string('thesis_back_list', 'local_kaznu'), ['class' => 'kz-thesis-link']);
echo html_writer::tag('h2', get_string('thesis_nav_queue', 'local_kaznu'), ['class' => 'kz-thesis-h']);
echo html_writer::div(get_string('thesis_disclaimer', 'local_kaznu'), 'kz-thesis-alert kz-thesis-alert-info');

if (!$rows) {
    echo html_writer::div(get_string('thesis_queue_empty', 'local_kaznu'), 'kz-thesis-empty');
} else {
    echo html_writer::start_div('kz-thesis-list');
    foreach ($rows as $r) {
        $student = core_user::get_user((int) $r->userid);
        $report = thesis_service::decode_report($r->reportjson);
        echo html_writer::start_div('kz-thesis-card');
        echo html_writer::tag('h3', format_string($r->title), ['class' => 'kz-thesis-card-title']);
        echo html_writer::div(thesis_service::status_label($r->status), 'kz-thesis-badge kz-thesis-badge-' . $r->status);
        echo html_writer::tag('p', get_string('thesis_col_student', 'local_kaznu') . ': ' . ($student ? fullname($student) : $r->userid), ['class' => 'kz-thesis-meta']);
        $ai = (int) ($report['ai']['score'] ?? 0);
        $orig = (int) ($report['plagiarism']['originality'] ?? 0);
        echo html_writer::tag('p', 'ИИ: ' . $ai . '% · Оригинальность: ' . $orig . '%', ['class' => 'kz-thesis-meta']);
        echo html_writer::link(
            new moodle_url('/local/kaznu/thesis/review.php', ['id' => $r->id]),
            get_string('thesis_review_action', 'local_kaznu'),
            ['class' => 'kz-thesis-btn kz-thesis-btn-primary']
        );
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
}
echo html_writer::end_div();
echo $OUTPUT->footer();
