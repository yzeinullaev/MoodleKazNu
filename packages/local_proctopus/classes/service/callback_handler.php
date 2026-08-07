<?php
namespace local_proctopus\service;

use local_proctopus\callback\callback_verifier_interface;
use local_proctopus\callback\db_replay_guard;
use local_proctopus\callback\disabled_verifier;
use local_proctopus\callback\replay_guard_interface;

defined('MOODLE_INTERNAL') || die();

/**
 * Processes callback HTTP body. Stores payload_hash only.
 */
class callback_handler {
    /**
     * @return array{http:int, body:array}
     */
    public static function handle(
        string $raw,
        string $ctype,
        string $correlation,
        ?callback_verifier_interface $verifier = null,
        ?replay_guard_interface $replay = null
    ): array {
        if (!local_proctopus_is_enabled()) {
            return [
                'http' => 503,
                'body' => [
                    'ok' => false,
                    'error' => 'integration_disabled',
                    'correlation' => $correlation,
                ],
            ];
        }

        $verifier = $verifier ?? new disabled_verifier();
        $check = $verifier->verify($raw, $ctype, $correlation);
        if (empty($check['ok'])) {
            $err = $check['error'] ?? 'rejected';
            $http = $err === 'not_configured' ? 503 : 401;
            return [
                'http' => $http,
                'body' => [
                    'ok' => false,
                    'error' => $err,
                    'correlation' => $correlation,
                ],
            ];
        }

        $claims = $check['claims'] ?? [];
        $eventid = (string) ($claims['eventId'] ?? $claims['jti'] ?? '');
        $hash = hash('sha256', $raw);
        $replay = $replay ?? new db_replay_guard();
        if (!$replay->accept($eventid, $hash)) {
            return [
                'http' => 409,
                'body' => [
                    'ok' => true,
                    'duplicate' => true,
                    'correlation' => $correlation,
                ],
            ];
        }

        // Map only via external session id — never trust raw userId alone (IDOR).
        global $DB;
        $externalsession = (string) ($claims['sessionId'] ?? '');
        $session = null;
        if ($externalsession !== '' && $DB->get_manager()->table_exists('local_proctopus_session')) {
            $session = $DB->get_record('local_proctopus_session', ['external_session_id' => $externalsession]);
        }
        $sessionid = $session ? (int) $session->id : 0;

        if ($DB->get_manager()->table_exists('local_proctopus_callback')) {
            $DB->insert_record('local_proctopus_callback', (object) [
                'sessionid' => $sessionid,
                'external_event_id' => $eventid !== '' ? $eventid : ('auto_' . $correlation),
                'event_type' => (string) ($claims['event'] ?? 'result'),
                'safe_status' => (string) ($claims['status'] ?? 'unknown'),
                'payload_hash' => $hash,
                'received_at' => time(),
                'processed_at' => time(),
                'processing_status' => 'processed',
                'error_code' => '',
            ]);
        }

        // Safe local status/result only — never touch Moodle grades or attempts.
        if ($session) {
            $safecode = (string) ($claims['status'] ?? $claims['result'] ?? 'received');
            $safesummary = (string) ($claims['event'] ?? 'provider_result');
            // Strip accidental secrets from freeform fields (defense in depth).
            if (preg_match('/eyJ[A-Za-z0-9_-]{10,}/', $safecode . $safesummary)) {
                $safecode = 'provider_status';
                $safesummary = 'provider_result';
            }
            proctoring_session_reconciler::apply_provider_result($session, $safecode, $safesummary);
        }

        return [
            'http' => 200,
            'body' => [
                'ok' => true,
                'correlation' => $correlation,
            ],
        ];
    }
}
