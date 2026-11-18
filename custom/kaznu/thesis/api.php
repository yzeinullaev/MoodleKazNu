<?php
/**
 * Thesis AJAX API: AI check + plagiarism check.
 *
 * @package local_kaznu
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

use local_kaznu\service\thesis_service;

require_login();
require_sesskey();
thesis_service::require_enabled();

$sys = context_system::instance();
$cansupervise = has_capability('local/kaznu:thesissupervise', $sys)
    || has_capability('local/kaznu:thesismanage', $sys)
    || is_siteadmin();

if (!local_kaznu_thesis_can_upload() && !$cansupervise) {
    echo json_encode(['ok' => false, 'error' => get_string('student_preview_denied', 'local_kaznu')], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$action = required_param('action', PARAM_ALPHANUMEXT);
$id = required_param('id', PARAM_INT);

try {
    if ($action === 'ai') {
        $report = thesis_service::run_ai_check($id, (int) $USER->id);
        echo json_encode(['ok' => true, 'report' => $report], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'plagiarism') {
        $report = thesis_service::run_plagiarism_check($id, (int) $USER->id);
        echo json_encode(['ok' => true, 'report' => $report], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'eds_payload') {
        if (!$cansupervise) {
            throw new moodle_exception('thesis_error_forbidden', 'local_kaznu');
        }
        $sub = thesis_service::get($id);
        if (!$sub || !thesis_service::can_access($sub, (int) $USER->id, is_siteadmin(), $cansupervise)) {
            throw new moodle_exception('thesis_error_forbidden', 'local_kaznu');
        }
        $sig = thesis_service::get_signature($id);
        $stubonly = $sub->status === thesis_service::STATUS_APPROVED
            && $sig && ($sig->sigtype ?? '') === 'stub';
        if ($sub->status !== thesis_service::STATUS_SUBMITTED && !$stubonly) {
            throw new moodle_exception('thesis_error_locked', 'local_kaznu');
        }
        echo json_encode(['ok' => true, 'payload' => thesis_service::build_eds_sign_payload($id)], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'eds_sign') {
        if (!$cansupervise) {
            throw new moodle_exception('thesis_error_forbidden', 'local_kaznu');
        }
        $sub = thesis_service::get($id);
        if (!$sub || !thesis_service::can_access($sub, (int) $USER->id, is_siteadmin(), $cansupervise)) {
            throw new moodle_exception('thesis_error_forbidden', 'local_kaznu');
        }
        $sigtype = required_param('sigtype', PARAM_ALPHANUMEXT);
        $signature = required_param('signature', PARAM_RAW);
        $comment = optional_param('comment', '', PARAM_TEXT);
        thesis_service::supervisor_approve_signed(
            $id,
            (int) $USER->id,
            $comment,
            $sigtype,
            $signature,
            is_siteadmin() || has_capability('local/kaznu:thesismanage', $sys)
        );
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'unknown_action'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $msg = $e instanceof moodle_exception
        ? get_string($e->errorcode, $e->module ?: 'error', $e->a)
        : $e->getMessage();
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
}
