<?php
/**
 * Thesis hub — three capabilities: AI, plagiarism, EDS.
 *
 * @package local_kaznu
 */

require_once(__DIR__ . '/../../../config.php');

use local_kaznu\service\thesis_service;
use local_kaznu\service\thesis_dba_analyzer;

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

echo html_writer::start_div('kz-thesis-actions');
echo html_writer::tag('button', get_string('thesis_ai_info_btn', 'local_kaznu'), [
    'type' => 'button', 'class' => 'kz-thesis-btn kz-thesis-btn-ghost', 'id' => 'kz-thesis-ai-info-open',
]);
echo html_writer::link(new moodle_url('/local/kaznu/thesis/edit.php'), get_string('thesis_new', 'local_kaznu'), ['class' => 'kz-thesis-btn kz-thesis-btn-primary']);
echo html_writer::end_div();

echo html_writer::start_div('kz-thesis-modal', ['id' => 'kz-thesis-ai-modal', 'hidden' => 'hidden', 'aria-hidden' => 'true']);
echo html_writer::start_div('kz-thesis-modal-backdrop', ['id' => 'kz-thesis-ai-modal-backdrop']);
echo html_writer::start_div('kz-thesis-modal-panel', ['role' => 'dialog', 'aria-modal' => 'true']);
echo html_writer::start_div('kz-thesis-modal-head');
echo html_writer::tag('h3', get_string('thesis_ai_info_title', 'local_kaznu'));
echo html_writer::tag('button', '×', ['type' => 'button', 'class' => 'kz-thesis-modal-close', 'id' => 'kz-thesis-ai-info-close', 'aria-label' => 'Закрыть']);
echo html_writer::end_div();
echo html_writer::tag('p', get_string('thesis_ai_info_lead', 'local_kaznu'), ['class' => 'kz-thesis-lead']);
foreach (thesis_dba_analyzer::ai_info_sections() as $section) {
    echo html_writer::tag('h4', s($section['title']), ['class' => 'kz-thesis-subh']);
    echo html_writer::start_tag('ul');
    foreach ($section['items'] as $item) {
        echo html_writer::tag('li', s($item));
    }
    echo html_writer::end_tag('ul');
}
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

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
echo html_writer::script("
(function(){
  var m=document.getElementById('kz-thesis-ai-modal');
  var open=document.getElementById('kz-thesis-ai-info-open');
  var close=document.getElementById('kz-thesis-ai-info-close');
  var bd=document.getElementById('kz-thesis-ai-modal-backdrop');
  function show(on){ if(!m)return; m.hidden=!on; m.setAttribute('aria-hidden', on?'false':'true'); document.body.style.overflow=on?'hidden':''; }
  if(open) open.addEventListener('click', function(){ show(true); });
  if(close) close.addEventListener('click', function(){ show(false); });
  if(bd) bd.addEventListener('click', function(){ show(false); });
})();
");
echo $OUTPUT->footer();