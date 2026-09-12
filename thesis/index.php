<?php
/**
 * Thesis hub — three capabilities: AI, plagiarism, EDS.
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
$PAGE->set_url(new moodle_url('/local/kaznu/thesis/index.php'));
$PAGE->set_context($sys);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('thesis_title', 'local_kaznu'));
$PAGE->set_heading(get_string('thesis_title', 'local_kaznu'));
$PAGE->requires->css(new moodle_url('/local/kaznu/styles.css'));

echo $OUTPUT->header();

if (!thesis_service::is_enabled()) {
    echo html_writer::div(get_string('thesis_disabled', 'local_kaznu'), 'kz-thesis-alert kz-thesis-alert-warn');
    echo html_writer::link(new moodle_url('/local/kaznu/my.php'), get_string('thesis_back_cabinet', 'local_kaznu'), ['class' => 'kz-thesis-link']);
    echo $OUTPUT->footer();
    exit;
}

if (!local_kaznu_thesis_can_upload()) {
    throw new moodle_exception('thesis_error_forbidden', 'local_kaznu');
}

$cansupervise = has_capability('local/kaznu:thesissupervise', $sys)
    || has_capability('local/kaznu:thesismanage', $sys)
    || is_siteadmin();
$rows = thesis_service::list_for_user((int) $USER->id);

echo html_writer::start_div('kz-thesis');
echo html_writer::start_div('kz-thesis-top');
echo html_writer::link(new moodle_url('/local/kaznu/my.php'), get_string('thesis_back_cabinet', 'local_kaznu'), ['class' => 'kz-thesis-link']);
if ($cansupervise) {
    echo html_writer::link(new moodle_url('/local/kaznu/thesis/queue.php'), get_string('thesis_nav_queue', 'local_kaznu'), ['class' => 'kz-thesis-link kz-thesis-link-strong']);
}
echo html_writer::end_div();

echo html_writer::tag('h2', get_string('thesis_title', 'local_kaznu'), ['class' => 'kz-thesis-h']);
echo html_writer::tag('p', get_string('thesis_lead', 'local_kaznu'), ['class' => 'kz-thesis-lead']);

echo html_writer::start_div('kz-thesis-pillars');
$pillars = [
    ['n' => '01', 't' => get_string('thesis_pillar_ai', 'local_kaznu'), 'd' => get_string('thesis_pillar_ai_desc', 'local_kaznu')],
    ['n' => '02', 't' => get_string('thesis_pillar_plag', 'local_kaznu'), 'd' => get_string('thesis_pillar_plag_desc', 'local_kaznu')],
    ['n' => '03', 't' => get_string('thesis_pillar_eds', 'local_kaznu'), 'd' => get_string('thesis_pillar_eds_desc', 'local_kaznu')],
];
foreach ($pillars as $p) {
    echo html_writer::start_div('kz-thesis-pillar');
    echo html_writer::div($p['n'], 'kz-thesis-pillar-n');
    echo html_writer::tag('h3', $p['t']);
    echo html_writer::tag('p', $p['d']);
    echo html_writer::end_div();
}
echo html_writer::end_div();

echo html_writer::div(get_string('thesis_disclaimer', 'local_kaznu'), 'kz-thesis-alert kz-thesis-alert-info');

echo html_writer::div(
    html_writer::link(new moodle_url('/local/kaznu/thesis/edit.php'), get_string('thesis_new', 'local_kaznu'), ['class' => 'kz-thesis-btn kz-thesis-btn-primary']),
    'kz-thesis-actions'
);

if (!$rows) {
    echo html_writer::div(get_string('thesis_empty', 'local_kaznu'), 'kz-thesis-empty');
} else {
    echo html_writer::start_div('kz-thesis-list');
    foreach ($rows as $r) {
        $report = thesis_service::decode_report($r->reportjson);
        $steps = thesis_service::step_state($report, $r->status);
        echo html_writer::start_div('kz-thesis-card');
        echo html_writer::tag('h3', format_string($r->title), ['class' => 'kz-thesis-card-title']);
        echo html_writer::div(thesis_service::status_label($r->status), 'kz-thesis-badge kz-thesis-badge-' . $r->status);
        echo html_writer::start_div('kz-thesis-mini-steps');
        foreach (['ai' => 'ИИ', 'plagiarism' => 'Антиплагиат', 'eds' => 'ЭЦП'] as $k => $label) {
            $st = $steps[$k] ?? 'todo';
            echo html_writer::span($label, 'kz-thesis-mini kz-thesis-mini-' . $st);
        }
        echo html_writer::end_div();
        echo html_writer::tag('p', userdate((int) $r->timemodified), ['class' => 'kz-thesis-meta']);
        echo html_writer::link(
            new moodle_url('/local/kaznu/thesis/report.php', ['id' => $r->id]),
            get_string('thesis_open', 'local_kaznu'),
            ['class' => 'kz-thesis-btn kz-thesis-btn-ghost']
        );
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
}

echo html_writer::end_div();
echo $OUTPUT->footer();
