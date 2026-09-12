<?php
/**
 * JSON API for Farabi student AI chat.
 *
 * POST: sesskey, message, history (optional JSON array)
 *
 * @package local_kaznu
 */

define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/enrollib.php');

use local_kaznu\service\ai_chat_service;

header('Content-Type: application/json; charset=utf-8');

/**
 * Always emit JSON — never let Moodle HTML/500 leak to the browser.
 *
 * @param array $payload
 * @param int $http
 */
function local_kaznu_ai_json(array $payload, int $http = 200): void {
    if (!headers_sent()) {
        http_response_code($http);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_login();
    require_sesskey();

    if (isguestuser()) {
        local_kaznu_ai_json(['ok' => false, 'error' => 'guest', 'message' => 'Guest not allowed']);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        local_kaznu_ai_json(['ok' => false, 'error' => 'method', 'message' => 'POST required']);
    }

    $message = trim(optional_param('message', '', PARAM_RAW));
    $historyraw = optional_param('history', '[]', PARAM_RAW);
    $history = json_decode($historyraw, true);
    if (!is_array($history)) {
        $history = [];
    }

    // Rate limit: 20 / 10 minutes per session.
    global $SESSION, $USER;
    $now = time();
    if (empty($SESSION->local_kaznu_ai_rl) || !is_array($SESSION->local_kaznu_ai_rl)
            || empty($SESSION->local_kaznu_ai_rl['t']) || ($now - (int) $SESSION->local_kaznu_ai_rl['t']) > 600) {
        $SESSION->local_kaznu_ai_rl = ['t' => $now, 'n' => 0];
    }
    $SESSION->local_kaznu_ai_rl['n'] = (int) $SESSION->local_kaznu_ai_rl['n'] + 1;
    if ($SESSION->local_kaznu_ai_rl['n'] > 20) {
        local_kaznu_ai_json([
            'ok' => false,
            'error' => 'rate_limit',
            'message' => get_string('ai_chat_error_rate_limit', 'local_kaznu'),
        ]);
    }

    $service = new ai_chat_service();
    $result = $service->chat($USER, $message, $history);

    $errmap = [
        'empty' => get_string('ai_chat_error_empty', 'local_kaznu'),
        'too_long' => get_string('ai_chat_error_too_long', 'local_kaznu'),
        'not_configured' => get_string('ai_chat_error_not_configured', 'local_kaznu'),
        'disabled' => get_string('ai_chat_error_disabled', 'local_kaznu'),
        'provider' => get_string('ai_chat_error_provider', 'local_kaznu'),
        'parse' => get_string('ai_chat_error_provider', 'local_kaznu'),
        'encode' => get_string('ai_chat_error_provider', 'local_kaznu'),
        'empty_reply' => get_string('ai_chat_error_provider', 'local_kaznu'),
        'rate_limit' => get_string('ai_chat_error_rate_limit', 'local_kaznu'),
        'exception' => get_string('ai_chat_error_provider', 'local_kaznu'),
    ];

    if (empty($result['ok'])) {
        $code = (string) ($result['error'] ?? 'provider');
        local_kaznu_ai_json([
            'ok' => false,
            'error' => $code,
            'message' => $errmap[$code] ?? $errmap['provider'],
        ]);
    }

    local_kaznu_ai_json([
        'ok' => true,
        'reply' => (string) $result['reply'],
    ]);
} catch (\Throwable $e) {
    debugging('local_kaznu chat_api: ' . $e->getMessage(), DEBUG_DEVELOPER);
    local_kaznu_ai_json([
        'ok' => false,
        'error' => 'exception',
        'message' => get_string('ai_chat_error_provider', 'local_kaznu'),
    ], 200);
}
