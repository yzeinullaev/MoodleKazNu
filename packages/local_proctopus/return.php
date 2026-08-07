<?php
/**
 * Signed return from proctoring (or stub).
 * Validates state TTL/user/cmid; marks session admitted; hands off to Moodle startattempt.
 */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_proctopus\service\attempt_bridge;
use local_proctopus\service\session_service;

require_login();

$state = required_param('state', PARAM_RAW_TRIMMED);
$PAGE->set_url('/local/proctopus/return.php');
$PAGE->set_context(context_system::instance());

try {
    $svc = new session_service();
    $info = $svc->complete_return((int) $USER->id, $state);
    attempt_bridge::redirect_to_standard_start((int) $info['cmid'], (int) $USER->id);
} catch (moodle_exception $e) {
    \core\notification::error(get_string('proctopus_invalid_return', 'local_proctopus'));
    redirect(new moodle_url('/my/'));
}
