<?php
namespace local_proctopus\callback;

defined('MOODLE_INTERNAL') || die();

/**
 * Stub HMAC verifier for offline tests only.
 * Production must use provider contract; this accepts only test envelopes.
 *
 * Envelope JSON:
 * { "eventId":"…", "sessionId":"…", "status":"done", "ts":123, "sig":"hex" }
 * sig = hash_hmac('sha256', eventId|sessionId|status|ts, secret)
 */
class stub_hmac_verifier implements callback_verifier_interface {
    public function verify(string $rawbody, string $contenttype, string $correlation): array {
        if (!str_contains(strtolower($contenttype), 'application/json') && $contenttype !== '') {
            return ['ok' => false, 'error' => 'content_type'];
        }
        $data = json_decode($rawbody, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'invalid_json'];
        }
        $eventid = (string) ($data['eventId'] ?? '');
        $session = (string) ($data['sessionId'] ?? '');
        $status = (string) ($data['status'] ?? '');
        $ts = (int) ($data['ts'] ?? 0);
        $sig = (string) ($data['sig'] ?? '');
        if ($eventid === '' || $session === '' || $sig === '') {
            return ['ok' => false, 'error' => 'missing_fields'];
        }
        $skew = (int) (get_config('local_proctopus', 'proctopus_clock_skew') ?: 60);
        if ($ts > 0 && abs(time() - $ts) > max(300, $skew * 10)) {
            return ['ok' => false, 'error' => 'timestamp'];
        }
        $secret = local_proctopus_get_jwt_secret();
        if ($secret === '') {
            if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
                $secret = 'phpunit-fixture-secret-not-for-prod';
            } else {
                return ['ok' => false, 'error' => 'not_configured'];
            }
        }
        $base = $eventid . '|' . $session . '|' . $status . '|' . $ts;
        $expect = hash_hmac('sha256', $base, $secret);
        if (!hash_equals($expect, $sig)) {
            return ['ok' => false, 'error' => 'bad_signature'];
        }
        return [
            'ok' => true,
            'claims' => [
                'eventId' => $eventid,
                'sessionId' => $session,
                'status' => $status,
                'event' => 'result',
            ],
        ];
    }
}
