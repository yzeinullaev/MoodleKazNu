<?php
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_sesskey();

header('Content-Type: application/json; charset=utf-8');

if (!local_kaznu_ai_chat_allowed()) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    die;
}

$action = required_param('action', PARAM_ALPHANUMEXT);
$userid = $USER->id;

use local_kaznu\ai_chat_service;

try {
    switch ($action) {
        case 'list':
            echo json_encode(['conversations' => ai_chat_service::list_conversations($userid)], JSON_UNESCAPED_UNICODE);
            break;

        case 'create':
            $id = ai_chat_service::create_conversation($userid);
            echo json_encode(['conversationid' => $id], JSON_UNESCAPED_UNICODE);
            break;

        case 'delete':
            $cid = required_param('conversationid', PARAM_INT);
            ai_chat_service::delete_conversation($userid, $cid);
            echo json_encode(['ok' => true]);
            break;

        case 'messages':
            $cid = required_param('conversationid', PARAM_INT);
            echo json_encode([
                'messages' => ai_chat_service::get_messages($userid, $cid),
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'send':
            $cid = optional_param('conversationid', 0, PARAM_INT);
            $text = required_param('message', PARAM_RAW_TRIMMED);
            $useweb = optional_param('useweb', 0, PARAM_BOOL);
            if ($text === '') {
                throw new moodle_exception('invaliddata');
            }
            $result = ai_chat_service::send_message($userid, $cid, $text, $useweb);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        default:
            throw new moodle_exception('invalidparameter');
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
