<?php
/**
 * Thesis workspace: AI + plagiarism + EDS.
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
$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$autoload = optional_param('autoload', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/kaznu/thesis/report.php', ['id' => $id]));
$PAGE->set_context($sys);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('thesis_report_title', 'local_kaznu'));
$PAGE->set_heading(get_string('thesis_report_title', 'local_kaznu'));
$PAGE->requires->css(new moodle_url('/local/kaznu/styles.css'));

thesis_service::require_enabled();
$sub = thesis_service::get($id);
if (!$sub) {
    throw new moodle_exception('thesis_error_forbidden', 'local_kaznu');
}

$ismanage = has_capability('local/kaznu:thesismanage', $sys) || is_siteadmin();
$cansupervise = has_capability('local/kaznu:thesissupervise', $sys);
$isowner = (int) $sub->userid === (int) $USER->id;
if (!thesis_service::can_access($sub, (int) $USER->id, $ismanage, $cansupervise)) {
    throw new moodle_exception('thesis_error_forbidden', 'local_kaznu');
}

if ($action === 'submit' && $isowner && confirm_sesskey()) {
    thesis_service::submit_to_supervisor($id, (int) $USER->id);
    redirect(new moodle_url('/local/kaznu/thesis/report.php', ['id' => $id]), get_string('thesis_submitted_ok', 'local_kaznu'));
}

$report = thesis_service::decode_report($sub->reportjson);
$steps = thesis_service::step_state($report, $sub->status);
$file = thesis_service::get_stored_file($id);
$sig = thesis_service::get_signature($id);
$apiurl = (new moodle_url('/local/kaznu/thesis/api.php'))->out(false);
$sesskey = sesskey();

echo $OUTPUT->header();
echo html_writer::start_div('kz-thesis', [
    'id' => 'kz-thesis-report',
    'data-id' => $id,
    'data-api' => $apiurl,
    'data-sesskey' => $sesskey,
    'data-autoload' => $autoload ? '1' : '0',
]);

echo html_writer::start_div('kz-thesis-top');
echo html_writer::link(new moodle_url('/local/kaznu/thesis/index.php'), get_string('thesis_back_list', 'local_kaznu'), ['class' => 'kz-thesis-link']);
echo html_writer::end_div();

echo html_writer::tag('h2', format_string($sub->title), ['class' => 'kz-thesis-h']);
echo html_writer::div(thesis_service::status_label($sub->status), 'kz-thesis-badge kz-thesis-badge-' . $sub->status);

echo html_writer::start_div('kz-thesis-stepper');
$labels = [
    'ai' => get_string('thesis_pillar_ai', 'local_kaznu'),
    'plagiarism' => get_string('thesis_pillar_plag', 'local_kaznu'),
    'eds' => get_string('thesis_pillar_eds', 'local_kaznu'),
];
$i = 1;
foreach ($labels as $key => $label) {
    $st = $steps[$key] ?? 'todo';
    echo html_writer::div(
        html_writer::span((string) $i, 'kz-thesis-step-num') . html_writer::span($label, 'kz-thesis-step-label'),
        'kz-thesis-step kz-thesis-step-' . $st
    );
    $i++;
}
echo html_writer::end_div();

echo html_writer::div(get_string('thesis_disclaimer', 'local_kaznu'), 'kz-thesis-alert kz-thesis-alert-info');

if (!empty($sub->supervisorcomment)) {
    echo html_writer::div(
        html_writer::tag('strong', get_string('thesis_supervisor_comment', 'local_kaznu') . ': ') . s($sub->supervisorcomment),
        'kz-thesis-alert kz-thesis-alert-warn'
    );
}

if ($file) {
    $url = moodle_url::make_pluginfile_url(
        $file->get_contextid(), $file->get_component(), $file->get_filearea(),
        $file->get_itemid(), $file->get_filepath(), $file->get_filename()
    );
    echo html_writer::div(
        get_string('thesis_download_file', 'local_kaznu') . ': '
        . html_writer::link($url, s($file->get_filename()), ['class' => 'kz-thesis-link-strong']),
        'kz-thesis-filebar'
    );
}

// --- Panel 1: AI ---
$ai = $report['ai'];
echo html_writer::start_div('kz-thesis-panel', ['id' => 'kz-panel-ai']);
echo html_writer::start_div('kz-thesis-panel-head');
echo html_writer::tag('h3', '1. ' . get_string('thesis_pillar_ai', 'local_kaznu'));
echo html_writer::span(($ai['status'] === 'done' ? get_string('thesis_done', 'local_kaznu') : get_string('thesis_pending', 'local_kaznu')), 'kz-thesis-chip');
echo html_writer::end_div();
echo html_writer::tag('p', get_string('thesis_pillar_ai_desc', 'local_kaznu'), ['class' => 'kz-thesis-panel-desc']);
if ($isowner && in_array($sub->status, ['draft', 'checked', 'revision'], true)) {
    echo html_writer::tag('button', get_string('thesis_run_ai', 'local_kaznu'), [
        'type' => 'button', 'class' => 'kz-thesis-btn kz-thesis-btn-primary', 'id' => 'kz-run-ai',
    ]);
}
echo html_writer::div(
    html_writer::div('', 'kz-thesis-spinner') . html_writer::span(get_string('thesis_loading_ai', 'local_kaznu')),
    'kz-thesis-loader', ['id' => 'kz-loader-ai', 'hidden' => 'hidden']
);
echo html_writer::div('', 'kz-thesis-panel-body', ['id' => 'kz-ai-body']);
echo html_writer::end_div();

// --- Panel 2: Plagiarism ---
$pl = $report['plagiarism'];
echo html_writer::start_div('kz-thesis-panel', ['id' => 'kz-panel-plag']);
echo html_writer::start_div('kz-thesis-panel-head');
echo html_writer::tag('h3', '2. ' . get_string('thesis_pillar_plag', 'local_kaznu'));
echo html_writer::span(($pl['status'] === 'done' ? get_string('thesis_done', 'local_kaznu') : get_string('thesis_pending', 'local_kaznu')), 'kz-thesis-chip');
echo html_writer::end_div();
echo html_writer::tag('p', get_string('thesis_pillar_plag_desc', 'local_kaznu'), ['class' => 'kz-thesis-panel-desc']);
if ($isowner && in_array($sub->status, ['draft', 'checked', 'revision'], true)) {
    echo html_writer::tag('button', get_string('thesis_run_plag', 'local_kaznu'), [
        'type' => 'button', 'class' => 'kz-thesis-btn kz-thesis-btn-primary', 'id' => 'kz-run-plag',
    ]);
}
echo html_writer::div(
    html_writer::div('', 'kz-thesis-spinner') . html_writer::span(get_string('thesis_loading_plag', 'local_kaznu')),
    'kz-thesis-loader', ['id' => 'kz-loader-plag', 'hidden' => 'hidden']
);
echo html_writer::div('', 'kz-thesis-panel-body', ['id' => 'kz-plag-body']);
echo html_writer::end_div();

// --- Panel 3: EDS ---
echo html_writer::start_div('kz-thesis-panel', ['id' => 'kz-panel-eds']);
echo html_writer::start_div('kz-thesis-panel-head');
echo html_writer::tag('h3', '3. ' . get_string('thesis_pillar_eds', 'local_kaznu'));
$edslabel = !empty($report['eds']['signed']) ? get_string('thesis_signed', 'local_kaznu') : get_string('thesis_eds_waiting', 'local_kaznu');
echo html_writer::span($edslabel, 'kz-thesis-chip');
echo html_writer::end_div();
echo html_writer::tag('p', get_string('thesis_pillar_eds_desc', 'local_kaznu'), ['class' => 'kz-thesis-panel-desc']);
echo html_writer::div(get_string('thesis_eds_stub_note', 'local_kaznu'), 'kz-thesis-alert kz-thesis-alert-warn');
if ($sig) {
    echo html_writer::start_div('kz-thesis-eds-card');
    echo html_writer::tag('div', get_string('thesis_eds_ok', 'local_kaznu'), ['class' => 'kz-thesis-eds-ok']);
    echo html_writer::tag('p', s($sig->certsubject));
    echo html_writer::tag('p', 'Hash: ' . s(substr($sig->filehash, 0, 24)) . '…');
    echo html_writer::tag('p', userdate((int) $sig->timecreated));
    echo html_writer::end_div();
} else if ($sub->status === 'submitted' && ($ismanage || ((int) $sub->supervisorid === (int) $USER->id && $cansupervise))) {
    echo html_writer::link(
        new moodle_url('/local/kaznu/thesis/review.php', ['id' => $id]),
        get_string('thesis_review_action', 'local_kaznu'),
        ['class' => 'kz-thesis-btn kz-thesis-btn-primary']
    );
} else if ($isowner) {
    echo html_writer::tag('p', get_string('thesis_eds_student_hint', 'local_kaznu'), ['class' => 'kz-thesis-help']);
}
echo html_writer::end_div();

// Owner actions
if ($isowner && in_array($sub->status, ['draft', 'checked', 'revision'], true)) {
    echo html_writer::start_div('kz-thesis-actions');
    echo html_writer::link(new moodle_url('/local/kaznu/thesis/edit.php', ['id' => $id]), get_string('thesis_reupload', 'local_kaznu'), ['class' => 'kz-thesis-btn kz-thesis-btn-ghost']);
    echo html_writer::link(
        new moodle_url('/local/kaznu/thesis/report.php', ['id' => $id, 'action' => 'submit', 'sesskey' => sesskey()]),
        get_string('thesis_send_supervisor', 'local_kaznu'),
        ['class' => 'kz-thesis-btn kz-thesis-btn-primary', 'id' => 'kz-submit-sup']
    );
    echo html_writer::end_div();
}

$boot = [
    'report' => $report,
    'strings' => [
        'score' => get_string('thesis_score', 'local_kaznu'),
        'originality' => get_string('thesis_originality', 'local_kaznu'),
        'sources' => get_string('thesis_sources', 'local_kaznu'),
        'demo' => get_string('thesis_demo_badge', 'local_kaznu'),
        'pass' => get_string('thesis_pass', 'local_kaznu'),
        'fail' => get_string('thesis_fail', 'local_kaznu'),
        'error' => get_string('thesis_api_error', 'local_kaznu'),
        'done' => get_string('thesis_done', 'local_kaznu'),
    ],
];
echo html_writer::div('', '', ['id' => 'kz-thesis-boot', 'data-json' => json_encode($boot, JSON_UNESCAPED_UNICODE)]);

echo html_writer::script(<<<'JS'
(function () {
  var root = document.getElementById('kz-thesis-report');
  if (!root) return;
  var bootEl = document.getElementById('kz-thesis-boot');
  var boot = {};
  try { boot = JSON.parse(bootEl.getAttribute('data-json') || '{}'); } catch (e) { boot = {}; }
  var S = boot.strings || {};

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function renderAi(ai) {
    var box = document.getElementById('kz-ai-body');
    if (!box || !ai || ai.status !== 'done') { if (box) box.innerHTML = ''; return; }
    var html = '<div class="kz-thesis-score">' + esc((S.score || 'Score').replace('{$a}', ai.score)) +
      ' <span class="' + (ai.passed ? 'ok' : 'bad') + '">' + esc(ai.passed ? S.pass : S.fail) + '</span></div>';
    if (ai.summary) html += '<p class="kz-thesis-summary">' + esc(ai.summary) + '</p>';
    if (ai.checks && ai.checks.length) {
      html += '<ul class="kz-thesis-checks">';
      ai.checks.forEach(function (c) {
        html += '<li class="' + (c.ok ? 'ok' : 'bad') + '"><strong>' + esc(c.label || c.id) + '</strong>' +
          '<span>' + esc(c.found) + '</span></li>';
      });
      html += '</ul>';
    }
    if (ai.hints && ai.hints.length) {
      html += '<ul class="kz-thesis-hints">';
      ai.hints.forEach(function (h) { html += '<li>' + esc(h) + '</li>'; });
      html += '</ul>';
    }
    box.innerHTML = html;
  }

  function renderPlag(pl) {
    var box = document.getElementById('kz-plag-body');
    if (!box || !pl || pl.status !== 'done') { if (box) box.innerHTML = ''; return; }
    var html = '<div class="kz-thesis-score">' + esc(S.originality || 'Originality') + ': <b>' + esc(pl.originality) + '%</b>' +
      ' <span class="kz-thesis-demo">' + esc(S.demo || 'DEMO') + '</span>' +
      ' <span class="' + (pl.passed ? 'ok' : 'bad') + '">' + esc(pl.passed ? S.pass : S.fail) + '</span></div>';
    if (pl.summary) html += '<p class="kz-thesis-summary">' + esc(pl.summary) + '</p>';
    if (pl.sources && pl.sources.length) {
      html += '<p><strong>' + esc(S.sources || 'Sources') + '</strong></p><ul class="kz-thesis-sources">';
      pl.sources.forEach(function (s) {
        html += '<li><span>' + esc(s.title) + '</span><b>' + esc(s.percent) + '%</b></li>';
      });
      html += '</ul>';
    }
    box.innerHTML = html;
  }

  function setLoading(which, on) {
    var l = document.getElementById('kz-loader-' + which);
    var b = document.getElementById('kz-run-' + (which === 'plag' ? 'plag' : which));
    if (l) l.hidden = !on;
    if (b) b.disabled = !!on;
  }

  function run(action, which) {
    setLoading(which, true);
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('id', root.getAttribute('data-id'));
    body.set('sesskey', root.getAttribute('data-sesskey'));
    fetch(root.getAttribute('data-api'), {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); }).then(function (data) {
      setLoading(which, false);
      if (!data || !data.ok) {
        alert((data && data.error) ? data.error : (S.error || 'Error'));
        return;
      }
      if (action === 'ai') {
        renderAi(data.report.ai);
        var chip = document.querySelector('#kz-panel-ai .kz-thesis-chip');
        if (chip) chip.textContent = S.done || 'Done';
      }
      if (action === 'plagiarism') {
        renderPlag(data.report.plagiarism);
        var chip2 = document.querySelector('#kz-panel-plag .kz-thesis-chip');
        if (chip2) chip2.textContent = S.done || 'Done';
      }
    }).catch(function () {
      setLoading(which, false);
      alert(S.error || 'Error');
    });
  }

  var aiBtn = document.getElementById('kz-run-ai');
  var plBtn = document.getElementById('kz-run-plag');
  if (aiBtn) aiBtn.addEventListener('click', function () { run('ai', 'ai'); });
  if (plBtn) plBtn.addEventListener('click', function () { run('plagiarism', 'plag'); });

  renderAi((boot.report || {}).ai);
  renderPlag((boot.report || {}).plagiarism);

  if (root.getAttribute('data-autoload') === '1' && aiBtn) {
    setTimeout(function () { aiBtn.click(); }, 400);
  }
})();
JS
);

echo html_writer::end_div();
echo $OUTPUT->footer();
