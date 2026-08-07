<?php
/**
 * Offline smoke — never calls real Proctopus.
 * php local/proctopus/cli/offline_smoke.php
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

use local_proctopus\http\stub_transport;
use local_proctopus\jwt\hs_token_service;
use local_proctopus\service\callback_handler;

mtrace('proctopus offline smoke');

// 1 disabled mode
set_config('proctopus_enabled', 0, 'local_proctopus');
$r = callback_handler::handle('{}', 'application/json', 'smoke1');
mtrace('disabled callback http=' . $r['http'] . ' err=' . ($r['body']['error'] ?? ''));

// 2 JWT claims
$jwt = new hs_token_service();
$claims = $jwt->build_claims('sess1', 'farabi_business', 300);
assert(isset($claims['iat'], $claims['nbf'], $claims['exp'], $claims['jti']));
assert($claims['accountName'] === 'farabi_business');
mtrace('jwt claims ok accountName=' . $claims['accountName']);

// 3 encode with test secret (env not written)
$token = $jwt->encode($claims, 'unit-test-secret-not-production', 'HS256');
assert(!str_contains($token, 'unit-test-secret'));
mtrace('jwt encode ok segments=' . substr_count($token, '.'));

// 4 stub transport
$t = new stub_transport();
$t->request('POST', 'https://example.test/x', [], '{}');
mtrace('stub transport calls=' . count($t->calls));

// 5 no legacy
try {
    $t->request('GET', 'https://dl.kaznu.edu.kz/x?token=abc');
    mtrace('FAIL legacy allowed');
} catch (Throwable $e) {
    mtrace('legacy blocked ok');
}

mtrace('DONE');
