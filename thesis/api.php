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

if (!local_kaznu_thesis_can_upload()
        && !has_capability('local/kaznu:thesissupervise', context_system::instance())
        && !has_capability('local/kaznu:thesismanage', context_system::instance())
        && !is_siteadmin()) {
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
    echo json_encode(['ok' => false, 'error' => 'unknown_action'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $msg = $e instanceof moodle_exception
        ? get_string($e->errorcode, $e->module ?: 'error', $e->a)
        : $e->getMessage();
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
}
