<?php
/**
 * Offline P2 smoke — stub transport only. No real Proctopus HTTP.
 *
 * php local/proctopus/cli/p2_smoke.php
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

use local_proctopus\http\stub_transport;
use local_proctopus\jwt\hs_token_service;
use local_proctopus\jwt\legacy_payload_builder;
use local_proctopus\local\state_token;
use local_proctopus\service\callback_handler;
use local_proctopus\callback\stub_hmac_verifier;
use local_proctopus\api\legacy_proctopus_client;

mtrace('=== local_proctopus P2 offline smoke ===');

// Flags
mtrace('enabled=' . (int) local_proctopus_is_enabled());
mtrace('legacy=' . (int) local_proctopus_legacy_mode());
mtrace('live=' . (int) local_proctopus_live_calls_enabled());
if (local_proctopus_live_calls_enabled()) {
    cli_error('Refuse smoke while live calls enabled');
}

// JWT claims
$jwt = new hs_token_service();
$claims = $jwt->build_claims('s1', 'farabi_business', 300, ['iss' => 'farabischool.kz']);
assert($claims['accountName'] === 'farabi_business');
assert(isset($claims['iat'], $claims['nbf'], $claims['exp'], $claims['jti']));
$token = $jwt->encode($claims, 'offline-smoke-fixture-secret', 'HS256');
assert(substr_count($token, '.') === 2);
assert(!str_contains($token, 'offline-smoke-fixture-secret'));
mtrace('JWT encode OK');

// Payload domains
$user = (object) ['id' => 1, 'firstname' => 'T', 'lastname' => 'U', 'middlename' => '', 'username' => 'no'];
$quiz = (object) ['name' => 'Q1', 'timeopen' => time(), 'timeclose' => time() + 3600, 'timelimit' => 0];
$cm = (object) ['id' => 5, 'course' => 2, 'instance' => 1];
$payload = (new legacy_payload_builder())->build($user, $quiz, $cm, 'ext', 'st');
assert($payload['accountName'] === 'farabi_business');
assert($payload['thirdName'] === '');
assert(!str_contains(json_encode($payload), 'dl.kaznu'));
assert(!str_contains(json_encode($payload), 'kaznu_moodle'));
mtrace('Payload OK duration=' . $payload['duration']);

// State token
$st = state_token::issue(['sid' => 1, 'uid' => 2, 'cmid' => 3, 'ext' => 'e', 'exp' => time() + 60]);
assert(state_token::verify($st) !== null);
$bad = state_token::issue(['sid' => 1, 'uid' => 2, 'cmid' => 3, 'ext' => 'e', 'exp' => time() - 10]);
// re-issue with past exp already in array
assert(state_token::verify(
    state_token::issue(['sid' => 1, 'uid' => 2, 'cmid' => 3, 'ext' => 'e', 'exp' => time() - 9999])
) === null);
mtrace('State token OK');

// Legacy client stub
$stub = new stub_transport();
$stub->redirecturl = 'https://farabi-proctoring.kaznu.kz/ok';
$client = new legacy_proctopus_client($stub);
$r = $client->start($token, 'smoke');
assert(!empty($r['ok']));
assert(str_contains($stub->calls[0]['url'], '[redacted]'));
mtrace('Legacy stub start OK');

// Callback disabled when plugin disabled
set_config('proctopus_enabled', 0, 'local_proctopus');
$cb = callback_handler::handle('{}', 'application/json', 's');
assert($cb['http'] === 503);
mtrace('Callback disabled OK');

// No live curl
mtrace('REAL_HTTP=0');
mtrace('DONE');
