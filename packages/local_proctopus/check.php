<?php
/**
 * Proctoring preparation / equipment check page.
 * Does not create quiz attempts; does not call Proctopus until CTA after local checks.
 */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$cmid = required_param('cmid', PARAM_INT);
$error = optional_param('error', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$context = context_module::instance($cm->id);
require_capability('mod/quiz:attempt', $context);

if (!local_proctopus_is_enabled()) {
    throw new moodle_exception('proctopus_disabled', 'local_proctopus');
}

// If already admitted, send straight to quiz start (no need to re-check equipment).
if (class_exists(\local_proctopus\service\session_service::class)) {
    $svc = new \local_proctopus\service\session_service();
    if ($svc->user_has_admitted_session((int) $USER->id, $cmid)) {
        redirect(new moodle_url('/mod/quiz/view.php', ['id' => $cmid]));
    }
}

$PAGE->set_url('/local/proctopus/check.php', ['cmid' => $cmid]);
$PAGE->set_context($context);
$PAGE->set_cm($cm);
$PAGE->set_course($course);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('prep_title', 'local_proctopus'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('local-proctopus-prep');
$PAGE->add_body_class('local-proctopus-hide-achievements');

$PAGE->requires->css('/local/proctopus/styles.css');
$PAGE->requires->js(new moodle_url('/local/proctopus/check.js'), true);

$launchurl = (new moodle_url('/local/proctopus/launch.php', [
    'cmid' => $cmid,
    'confirm' => 1,
    'sesskey' => sesskey(),
]))->out(false);

$strings = [
    'status_pending' => get_string('check_status_pending', 'local_proctopus'),
    'status_checking' => get_string('check_status_checking', 'local_proctopus'),
    'status_success' => get_string('check_status_success', 'local_proctopus'),
    'status_warning' => get_string('check_status_warning', 'local_proctopus'),
    'status_error' => get_string('check_status_error', 'local_proctopus'),
    'camera_ok' => get_string('check_camera_ok', 'local_proctopus'),
    'camera_need' => get_string('check_camera_need', 'local_proctopus'),
    'camera_fail' => get_string('check_camera_fail', 'local_proctopus'),
    'mic_ok' => get_string('check_mic_ok', 'local_proctopus'),
    'mic_need' => get_string('check_mic_need', 'local_proctopus'),
    'mic_fail' => get_string('check_mic_fail', 'local_proctopus'),
    'screen_ok' => get_string('check_screen_ok', 'local_proctopus'),
    'screen_warn' => get_string('check_screen_warn', 'local_proctopus'),
    'conn_ok' => get_string('check_conn_ok', 'local_proctopus'),
    'conn_fail' => get_string('check_conn_fail', 'local_proctopus'),
    'ext_pending' => get_string('check_ext_pending', 'local_proctopus'),
    'ext_note' => get_string('check_ext_note', 'local_proctopus'),
    'cta_check' => get_string('cta_check_equipment', 'local_proctopus'),
    'cta_go_proctor' => get_string('cta_go_proctoring', 'local_proctopus'),
    'cta_retry' => get_string('cta_retry_check', 'local_proctopus'),
    'cta_retry_ext' => get_string('cta_retry_extension', 'local_proctopus'),
    'banner_ready' => get_string('prep_ready_banner', 'local_proctopus'),
    'banner_need' => get_string('prep_need_banner', 'local_proctopus'),
    'err_https' => get_string('err_https', 'local_proctopus'),
    'err_browser' => get_string('err_browser', 'local_proctopus'),
];

$configjson = json_encode([
    'cmid' => $cmid,
    'launchUrl' => $launchurl,
    'wwwroot' => $CFG->wwwroot,
    'strings' => $strings,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);

echo $OUTPUT->header();

echo local_proctopus_render_stepper('prep');

echo html_writer::start_div('fps-prep', [
    'id' => 'fps-prep',
    'data-fps-config' => $configjson,
]);

echo html_writer::tag('h2', get_string('prep_title', 'local_proctopus'), [
    'class' => 'fps-prep-title',
    'id' => 'fps-prep-title',
]);
echo html_writer::tag('p', get_string('prep_subtitle', 'local_proctopus'), [
    'class' => 'fps-prep-subtitle',
]);

if ($error === 'provider') {
    echo html_writer::div(
        get_string('err_provider', 'local_proctopus'),
        'fps-alert fps-alert-error',
        ['role' => 'alert']
    );
}

echo html_writer::div('', 'fps-banner', [
    'id' => 'fps-banner',
    'role' => 'status',
    'aria-live' => 'polite',
]);

$cards = [
    ['id' => 'camera', 'icon' => 'fa-video-camera', 'title' => 'check_card_camera', 'hint' => 'check_card_camera_hint'],
    ['id' => 'mic', 'icon' => 'fa-microphone', 'title' => 'check_card_mic', 'hint' => 'check_card_mic_hint'],
    ['id' => 'screen', 'icon' => 'fa-desktop', 'title' => 'check_card_screen', 'hint' => 'check_card_screen_hint'],
    ['id' => 'connection', 'icon' => 'fa-globe', 'title' => 'check_card_connection', 'hint' => 'check_card_connection_hint'],
    ['id' => 'extension', 'icon' => 'fa-shield', 'title' => 'check_card_extension', 'hint' => 'check_card_extension_hint'],
];

echo html_writer::start_div('fps-cards', ['role' => 'list']);
foreach ($cards as $card) {
    echo html_writer::start_div('fps-card', [
        'id' => 'fps-card-' . $card['id'],
        'data-check' => $card['id'],
        'role' => 'listitem',
        'aria-labelledby' => 'fps-card-' . $card['id'] . '-title',
    ]);
    echo html_writer::div(
        html_writer::tag('i', '', [
            'class' => 'icon fa ' . $card['icon'] . ' fa-fw fps-card-fa',
            'aria-hidden' => 'true',
        ]),
        'fps-card-icon',
        ['aria-hidden' => 'true']
    );
    echo html_writer::start_div('fps-card-body');
    echo html_writer::tag('h3', get_string($card['title'], 'local_proctopus'), [
        'class' => 'fps-card-title',
        'id' => 'fps-card-' . $card['id'] . '-title',
    ]);
    echo html_writer::tag('p', get_string($card['hint'], 'local_proctopus'), [
        'class' => 'fps-card-hint',
        'id' => 'fps-card-' . $card['id'] . '-detail',
    ]);
    echo html_writer::end_div();
    echo html_writer::tag('span', get_string('check_status_pending', 'local_proctopus'), [
        'class' => 'fps-card-status is-pending',
        'id' => 'fps-card-' . $card['id'] . '-status',
        'data-state' => 'pending',
    ]);
    echo html_writer::end_div();
}
echo html_writer::end_div();

echo html_writer::start_div('fps-actions');
echo html_writer::tag('button', html_writer::tag('i', '', [
        'class' => 'icon fa fa-check-circle fa-fw',
        'aria-hidden' => 'true',
    ]) . ' ' . get_string('cta_check_equipment', 'local_proctopus'), [
    'type' => 'button',
    'class' => 'btn btn-primary fps-cta',
    'id' => 'fps-cta',
    'data-mode' => 'check',
]);
echo html_writer::tag('button', get_string('cta_retry_check', 'local_proctopus'), [
    'type' => 'button',
    'class' => 'btn btn-secondary fps-cta-secondary d-none',
    'id' => 'fps-retry',
]);
echo html_writer::end_div();

// Hidden POST form for real launch (after local readiness).
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => (new moodle_url('/local/proctopus/launch.php'))->out(false),
    'id' => 'fps-launch-form',
    'class' => 'd-none',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cmid', 'value' => $cmid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'prep_ok', 'value' => 1]);
echo html_writer::end_tag('form');

echo html_writer::div(
    html_writer::link(
        new moodle_url('/mod/quiz/view.php', ['id' => $cmid]),
        get_string('prep_back_quiz', 'local_proctopus')
    ),
    'fps-back'
);

echo html_writer::end_div(); // fps-prep

echo $OUTPUT->footer();
