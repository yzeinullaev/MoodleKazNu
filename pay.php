<?php
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

require_capability('local/kaznu:viewpay', context_system::instance());

$action = optional_param('action', '', PARAM_ALPHA);
$token = optional_param('token', '', PARAM_ALPHANUMEXT);

$course = local_kaznu_get_demo_course();
if (!$course) {
    throw new moodle_exception('democoursenotfound', 'local_kaznu');
}

$confirmurl = local_kaznu_confirm_url();
$payurl = new moodle_url('/local/kaznu/pay.php');
$price = get_config('local_kaznu', 'paymentprice') ?: '25 000 ₸';
$wwwconfirm = $confirmurl->out(false);

// Confirm payment → enrol and open course (QR opens this URL on phone).
if ($action === 'confirm') {
    if ($token !== local_kaznu_payment_token()) {
        throw new moodle_exception('invalidtoken', 'local_kaznu');
    }

    // Must log in first (phone has no session) — before any capability check.
    require_login(null, false, $confirmurl);

    $syscontext = context_system::instance();
    if (!has_capability('local/kaznu:enrolself', $syscontext)) {
        // Demo fallback: any logged-in user may enrol after "payment".
        if (isguestuser()) {
            throw new moodle_exception('nopermissions', 'error', '', 'local/kaznu:enrolself');
        }
    }

    local_kaznu_enrol_user($course, (int) $USER->id, 'student');
    redirect(
        new moodle_url('/course/view.php', ['id' => $course->id]),
        get_string('enrolsuccess', 'local_kaznu'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url($payurl);
$PAGE->set_pagetype('local-kaznu-pay');
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('paytitle', 'local_kaznu'));
$PAGE->set_heading(get_string('paytitle', 'local_kaznu'));
$PAGE->add_body_class('path-local-kaznu');
$PAGE->add_body_class('local-kaznu-pay-page');

require_once(__DIR__ . '/lib.php');
local_kaznu_load_styles();

$already = isloggedin() && !isguestuser() && local_kaznu_is_enrolled((int) $USER->id);
$qrencoded = urlencode($wwwconfirm);
$qrimg = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . $qrencoded;

echo $OUTPUT->header();

echo html_writer::start_div('local-kaznu-pay');
echo html_writer::tag('span', get_string('paybadge', 'local_kaznu'), ['class' => 'local-kaznu-badge']);
echo html_writer::tag('h2', format_string($course->fullname));
echo html_writer::tag('p', get_string('payintro', 'local_kaznu'), ['class' => 'local-kaznu-pay-lead']);
echo html_writer::tag('p', get_string('payloginhint', 'local_kaznu'), ['class' => 'local-kaznu-muted']);

echo html_writer::start_div('local-kaznu-pay-grid');
echo html_writer::start_div('local-kaznu-pay-card');
echo html_writer::tag('div', $price, ['class' => 'local-kaznu-pay-price']);
echo html_writer::tag('p', get_string('paydesc', 'local_kaznu'));
echo html_writer::start_div('local-kaznu-pay-qr');
echo html_writer::empty_tag('img', [
    'src' => $qrimg,
    'alt' => get_string('qralt', 'local_kaznu'),
    'width' => 220,
    'height' => 220,
]);
echo html_writer::end_div();
echo html_writer::tag('p', get_string('qrhint', 'local_kaznu'), ['class' => 'local-kaznu-muted']);
echo html_writer::end_div();

echo html_writer::start_div('local-kaznu-pay-card local-kaznu-pay-steps-card');
echo html_writer::tag('h3', get_string('paysteps', 'local_kaznu'));
echo html_writer::start_tag('ol');
echo html_writer::tag('li', get_string('paystep1', 'local_kaznu'));
echo html_writer::tag('li', get_string('paystep2', 'local_kaznu'));
echo html_writer::tag('li', get_string('paystep3', 'local_kaznu'));
echo html_writer::end_tag('ol');

if ($already) {
    echo html_writer::link(
        new moodle_url('/course/view.php', ['id' => $course->id]),
        get_string('opencourse', 'local_kaznu'),
        ['class' => 'btn btn-primary btn-lg local-kaznu-btn']
    );
} else {
    $btnurl = isloggedin() && !isguestuser()
        ? $confirmurl
        : new moodle_url('/login/index.php', ['wantsurl' => $confirmurl->out(false)]);
    echo html_writer::link(
        $btnurl,
        get_string('paybutton', 'local_kaznu'),
        ['class' => 'btn btn-primary btn-lg local-kaznu-btn']
    );
    echo html_writer::tag('p', get_string('paylinkhint', 'local_kaznu', $wwwconfirm), ['class' => 'local-kaznu-muted local-kaznu-pay-link']);
}
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('local-kaznu-pay-features');
$features = [
    ['icon' => '🎬', 'text' => get_string('featvideo', 'local_kaznu')],
    ['icon' => '🎧', 'text' => get_string('feataudio', 'local_kaznu')],
    ['icon' => '📝', 'text' => get_string('feattests', 'local_kaznu')],
    ['icon' => '🎓', 'text' => get_string('featcert', 'local_kaznu')],
];
foreach ($features as $f) {
    echo html_writer::start_div('local-kaznu-pay-feat');
    echo html_writer::tag('span', $f['icon'], ['class' => 'local-kaznu-pay-feat-icon']);
    echo html_writer::tag('span', $f['text']);
    echo html_writer::end_div();
}
echo html_writer::end_div();

echo html_writer::end_div();
echo $OUTPUT->footer();
