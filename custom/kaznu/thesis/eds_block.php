<?php
/**
 * Supervisor EDS block (NCALayer + eGov mobile QR + upload).
 *
 * Expected: $id, $sub, $PAGE (moodle_page), $forcenew (bool, optional).
 *
 * @package local_kaznu
 */

defined('MOODLE_INTERNAL') || die();

use local_kaznu\service\thesis_service;

$forcenew = !empty($forcenew);

echo html_writer::div(get_string('thesis_eds_howto', 'local_kaznu'), 'kz-thesis-alert kz-thesis-alert-info');

$docurl = (new moodle_url('/local/kaznu/thesis/eds_doc.php', [
    'id' => $id,
    'sesskey' => sesskey(),
]))->out(false);
$qrimg = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=8&data=' . rawurlencode($docurl);

echo html_writer::start_div('kz-thesis-eds-egov-qr');
echo html_writer::tag('p', get_string('thesis_eds_qr_lead', 'local_kaznu'), ['class' => 'kz-thesis-help']);
echo html_writer::empty_tag('img', [
    'src' => $qrimg,
    'alt' => get_string('thesis_eds_qr_alt', 'local_kaznu'),
    'class' => 'kz-thesis-eds-qr',
    'width' => 200,
    'height' => 200,
]);
echo html_writer::link($docurl, get_string('thesis_eds_download_xml', 'local_kaznu'), ['class' => 'kz-thesis-link-strong']);
echo html_writer::tag('p', get_string('thesis_eds_qr_phone', 'local_kaznu'), ['class' => 'kz-thesis-help']);
echo html_writer::end_div();

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $PAGE->url->out(false),
    'class' => 'kz-thesis-form',
    'id' => 'kz-eds-form',
    'onsubmit' => 'return false;',
]);
echo html_writer::tag('label', get_string('thesis_supervisor_comment', 'local_kaznu'), ['class' => 'kz-thesis-label']);
echo html_writer::tag('textarea', '', [
    'name' => 'comment',
    'class' => 'kz-thesis-input',
    'rows' => 3,
    'placeholder' => $forcenew ? get_string('thesis_eds_resign_note', 'local_kaznu') : '',
]);
echo html_writer::start_div('kz-thesis-eds-block');
echo html_writer::tag('p', get_string('thesis_eds_nca_step', 'local_kaznu'), ['class' => 'kz-thesis-help']);
echo html_writer::tag('button', get_string('thesis_eds_nca_btn', 'local_kaznu'), [
    'type' => 'button',
    'class' => 'kz-thesis-btn kz-thesis-btn-primary',
    'id' => 'kz-eds-nca',
]);
echo html_writer::tag('p', get_string('thesis_eds_egov_step', 'local_kaznu'), ['class' => 'kz-thesis-help']);
echo html_writer::empty_tag('input', [
    'type' => 'file',
    'id' => 'kz-eds-egov-file',
    'class' => 'kz-thesis-file',
    'accept' => '.p7s,.cms,.bin,application/pkcs7-signature',
]);
echo html_writer::tag('button', get_string('thesis_eds_egov_btn', 'local_kaznu'), [
    'type' => 'button',
    'class' => 'kz-thesis-btn kz-thesis-btn-primary',
    'id' => 'kz-eds-egov',
]);
echo html_writer::end_div();
echo html_writer::end_tag('form');

echo html_writer::div(
    html_writer::div('', 'kz-thesis-spinner') . html_writer::span(get_string('thesis_loading_eds', 'local_kaznu')),
    'kz-thesis-loader',
    ['id' => 'kz-loader-eds', 'hidden' => 'hidden']
);

$apicfg = json_encode([
    'api' => (new moodle_url('/local/kaznu/thesis/api.php'))->out(false),
    'sesskey' => sesskey(),
    'id' => $id,
    'redirect' => (new moodle_url('/local/kaznu/thesis/report.php', ['id' => $id]))->out(false),
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

echo html_writer::script("window.kzThesisEdsInit({$apicfg});");