<?php
/**
 * Server-to-server Proctopus callback.
 * Rewrite (ops only, not prod yet): /proctoring/proctopus/result → this file.
 *
 * No require_login. POST + JSON only. Production verification OFF until contract.
 */
define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_proctopus\callback\disabled_verifier;
use local_proctopus\callback\stub_hmac_verifier;
use local_proctopus\service\callback_handler;

$correlation = bin2hex(random_bytes(8));
header('X-Correlation-Id: ' . $correlation);

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed', 'correlation' => $correlation]);
    exit;
}

$ctype = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if ($ctype !== '' && !str_starts_with($ctype, 'application/json')) {
    http_response_code(415);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'unsupported_media_type', 'correlation' => $correlation]);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
if (strlen($raw) > 65536) {
    http_response_code(413);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'payload_too_large', 'correlation' => $correlation]);
    exit;
}

// Production default: disabled verifier unless stub env explicitly set for tests.
$verifier = new disabled_verifier();
if ((defined('PHPUNIT_TEST') && PHPUNIT_TEST)
        || getenv('PROCTOPUS_CALLBACK_STUB') === '1') {
    $verifier = new stub_hmac_verifier();
}

$result = callback_handler::handle($raw, $ctype, $correlation, $verifier);
http_response_code((int) $result['http']);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($result['body']);
