<?php
/**
 * Supervisor EDS block (NCALayer + eGov mobile QR + upload).
 *
 * Expected: $id, $sub, $PAGE (moodle_page), $forcenew (bool, optional).
 *
 * @package local_kaznu
 */

defined('MOODLE_INTERNAL') || die();

$forcenew = !empty($forcenew);
$s = 'local_kaznu_thesis_str';

echo html_writer::div($s('thesis_eds_howto'), 'kz-thesis-alert kz-thesis-alert-info');

$docurl = (new moodle_url('/local/kaznu/thesis/eds_doc.php', [
    'id' => $id,
    'sesskey' => sesskey(),
]))->out(false);
$qrimg = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=8&data=' . rawurlencode($docurl);

echo html_writer::start_div('kz-thesis-eds-egov-qr');
echo html_writer::tag('h4', 'Подпись через eGov mobile', ['class' => 'kz-thesis-subh']);
echo html_writer::tag('p', $s('thesis_eds_qr_lead'), ['class' => 'kz-thesis-help']);
echo html_writer::empty_tag('img', [
    'src' => $qrimg,
    'alt' => $s('thesis_eds_qr_alt'),
    'class' => 'kz-thesis-eds-qr',
    'width' => 200,
    'height' => 200,
]);
echo html_writer::link($docurl, $s('thesis_eds_download_xml'), ['class' => 'kz-thesis-link-strong']);
echo html_writer::tag('p', $s('thesis_eds_qr_phone'), ['class' => 'kz-thesis-help']);
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
    'placeholder' => $forcenew ? $s('thesis_eds_resign_note') : '',
]);
echo html_writer::start_div('kz-thesis-eds-block');
echo html_writer::tag('h4', 'Подпись на компьютере (NCALayer)', ['class' => 'kz-thesis-subh']);
echo html_writer::tag('p', $s('thesis_eds_nca_step'), ['class' => 'kz-thesis-help']);
echo html_writer::tag('button', $s('thesis_eds_nca_btn'), [
    'type' => 'button',
    'class' => 'kz-thesis-btn kz-thesis-btn-primary',
    'id' => 'kz-eds-nca',
]);
echo html_writer::tag('h4', 'Или загрузить подпись с телефона', ['class' => 'kz-thesis-subh']);
echo html_writer::tag('p', $s('thesis_eds_egov_step'), ['class' => 'kz-thesis-help']);
echo html_writer::empty_tag('input', [
    'type' => 'file',
    'id' => 'kz-eds-egov-file',
    'class' => 'kz-thesis-file',
    'accept' => '.p7s,.cms,.bin,application/pkcs7-signature',
]);
echo html_writer::tag('button', $s('thesis_eds_egov_btn'), [
    'type' => 'button',
    'class' => 'kz-thesis-btn kz-thesis-btn-primary',
    'id' => 'kz-eds-egov',
]);
echo html_writer::end_div();
echo html_writer::end_tag('form');

echo html_writer::div(
    html_writer::div('', 'kz-thesis-spinner') . html_writer::span($s('thesis_loading_eds')),
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