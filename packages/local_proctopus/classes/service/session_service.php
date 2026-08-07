<?php
namespace local_proctopus\service;

use local_proctopus\api\legacy_proctopus_client;
use local_proctopus\http\proctopus_transport_interface;
use local_proctopus\jwt\hs_token_service;
use local_proctopus\jwt\legacy_payload_builder;
use local_proctopus\local\state_token;
use local_proctopus\repository\session_repository;
use local_proctopus\repository\session_repository_interface;

defined('MOODLE_INTERNAL') || die();

/**
 * Proctoring session orchestration. Never inserts quiz_attempts.
 */
class session_service {
    public function __construct(
        private readonly ?session_repository_interface $sessions = null,
        private readonly ?proctopus_transport_interface $transport = null,
        private readonly ?hs_token_service $jwt = null,
        private readonly ?legacy_payload_builder $payload = null
    ) {
    }

    private function sessions(): session_repository_interface {
        return $this->sessions ?? new session_repository();
    }

    private function jwt(): hs_token_service {
        return $this->jwt ?? new hs_token_service();
    }

    private function payload(): legacy_payload_builder {
        return $this->payload ?? new legacy_payload_builder();
    }

    /**
     * Active admitted session for this user/cm (gates prevent_access).
     * Always reconcile first so finished attempts cannot reuse stale admit rows.
     */
    public function user_has_admitted_session(int $userid, int $cmid): bool {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_proctopus_session')) {
            return false;
        }
        proctoring_session_reconciler::reconcile_user_cm($userid, $cmid);
        $now = time();
        $sql = "SELECT id FROM {local_proctopus_session}
                 WHERE userid = ? AND cmid = ? AND status = 'admitted'
                   AND (expires_at = 0 OR expires_at >= ?)
              ORDER BY id DESC";
        return (bool) $DB->get_field_sql($sql, [$userid, $cmid, $now], IGNORE_MULTIPLE);
    }

    /**
     * Launch: create session + JWT + legacy start (stub or live).
     *
     * @return array{redirect_url:string, correlation_id:string, sessionid:int, duplicate?:bool}
     */
    public function launch_for_cm(int $userid, int $cmid): array {
        global $DB, $USER;

        if (!local_proctopus_is_enabled()) {
            throw new \moodle_exception('proctopus_disabled', 'local_proctopus');
        }
        if ((int) $USER->id !== $userid) {
            throw new \moodle_exception('nopermissions', 'error', '', 'proctoring launch');
        }
        // Controlled live scope: refuse live start outside allowlisted cmid.
        if (local_proctopus_live_calls_enabled() && !local_proctopus_live_cmid_allowed($cmid)) {
            local_proctopus_safe_log('scope', 'live_cmid_denied_'.$cmid, 0, 0);
            throw new \moodle_exception('proctopus_start_failed', 'local_proctopus');
        }

        $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance((int) $cm->id);
        require_capability('mod/quiz:attempt', $context, $userid);

        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $user = \core_user::get_user($userid, '*', MUST_EXIST);

        $ruleset = null;
        if ($DB->get_manager()->table_exists('quizaccess_proctopus')) {
            $ruleset = $DB->get_record('quizaccess_proctopus', ['quizid' => $quiz->id]) ?: null;
        }

        // Never reuse sessions tied to finished attempts.
        proctoring_session_reconciler::reconcile_user_cm($userid, $cmid);

        $existing = $this->sessions()->get_active_for_user_cm($userid, $cmid);
        if ($existing && in_array($existing->status, ['created', 'started', 'admitted'], true)) {
            // Re-issue launch only for created/started; admitted users go to quiz.
            if ($existing->status === 'admitted') {
                return [
                    'redirect_url' => (new \moodle_url('/mod/quiz/view.php', ['id' => $cmid]))->out(false),
                    'correlation_id' => (string) $existing->correlation_id,
                    'sessionid' => (int) $existing->id,
                    'duplicate' => true,
                    'already_admitted' => true,
                ];
            }
        }

        if ($existing && $existing->status === 'created') {
            $id = (int) $existing->id;
            $external = (string) $existing->external_session_id;
            $correlation = (string) $existing->correlation_id;
        } else {
            $correlation = bin2hex(random_bytes(8));
            $external = 'fps_' . bin2hex(random_bytes(12));
            $ttl = local_proctopus_token_ttl();
            $id = $this->sessions()->create([
                'userid' => $userid,
                'courseid' => (int) $cm->course,
                'cmid' => $cmid,
                'quizid' => (int) $quiz->id,
                'external_session_id' => $external,
                'status' => 'created',
                'proctoring_mode' => (string) (get_config('local_proctopus', 'proctopus_default_mode') ?: 'online'),
                'started_at' => time(),
                'expires_at' => time() + $ttl,
                'correlation_id' => $correlation,
            ]);
        }

        $state = state_token::issue([
            'sid' => $id,
            'uid' => $userid,
            'cmid' => $cmid,
            'ext' => $external,
            'exp' => time() + local_proctopus_token_ttl(),
        ]);

        $claims = $this->payload()->build($user, $quiz, $cm, $external, $state, $ruleset ?: null);
        $alg = local_proctopus_jwt_algorithm();
        $secret = local_proctopus_get_jwt_secret();
        if ($secret === '') {
            // Offline tests may inject secret via env; production leaves empty.
            if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
                $secret = 'phpunit-fixture-secret-not-for-prod';
            } else if (!local_proctopus_live_calls_enabled()) {
                // Stub flow: synthetic secret for token structure tests only, not sent live.
                $secret = 'offline-stub-secret-not-for-prod';
            } else {
                throw new \moodle_exception('proctopus_not_configured', 'local_proctopus');
            }
        }

        // Controlled live preflight (no secrets/JWT in output).
        $account = (string) ($claims['accountName'] ?? '');
        $startpath = local_proctopus_start_path();
        if ($account === '' || $account === 'kaznu_moodle') {
            throw new \moodle_exception('proctopus_not_configured', 'local_proctopus');
        }
        if (!str_contains($startpath, 'farabi_business')) {
            throw new \moodle_exception('proctopus_not_configured', 'local_proctopus');
        }
        if (hash_equals('PotomDam', $secret) || $secret === 'PotomDam') {
            throw new \moodle_exception('proctopus_not_configured', 'local_proctopus');
        }
        $claimblob = json_encode($claims, JSON_UNESCAPED_UNICODE);
        if (is_string($claimblob) && (str_contains($claimblob, 'kaznu_moodle') || str_contains($claimblob, 'dl.kaznu'))) {
            throw new \moodle_exception('proctopus_not_configured', 'local_proctopus');
        }
        // Code.php-compatible legacy body: no registered JWT time claims.
        foreach (['iat', 'nbf', 'exp', 'jti', 'iss'] as $ban) {
            if (array_key_exists($ban, $claims)) {
                unset($claims[$ban]);
            }
        }

        // Encode once (HS256). Token never logged, never stored.
        $token = $this->jwt()->encode($claims, $secret, $alg);

        $returnurl = (new \moodle_url('/local/proctopus/return.php', ['state' => $state]))->out(false);

        $client = new legacy_proctopus_client($this->transport);
        if (!local_proctopus_live_calls_enabled()) {
            // Force stub path with return URL header for redirect.
            $stub = $this->transport ?? new \local_proctopus\http\stub_transport();
            if ($stub instanceof \local_proctopus\http\stub_transport) {
                $stub->redirecturl = $returnurl;
            }
            $client = new legacy_proctopus_client($stub);
            $result = $client->start($token, $correlation);
        } else {
            $result = $client->start($token, $correlation);
        }

        // Do not keep JWT in memory beyond this scope, never persist.
        unset($token);

        if (empty($result['ok']) || empty($result['redirect_url'])) {
            $this->sessions()->update_status($id, 'failed', [
                'result_code' => $result['error'] ?? 'start_failed',
                'result_summary' => 'start_failed',
                'completed_at' => time(),
            ]);
            local_proctopus_safe_log($correlation, 'start_failed', (int) ($result['http'] ?? 0), (int) ($result['duration_ms'] ?? 0));
            throw new \moodle_exception('proctopus_start_failed', 'local_proctopus');
        }

        $this->sessions()->update_status($id, 'started', [
            'result_summary' => 'redirected',
        ]);

        $redir = (string) $result['redirect_url'];
        if (!local_proctopus_is_redirect_host_allowed($redir)) {
            $this->sessions()->update_status($id, 'failed', [
                'result_code' => 'redirect_host_forbidden',
                'completed_at' => time(),
            ]);
            throw new \moodle_exception('proctopus_start_failed', 'local_proctopus');
        }

        return [
            'redirect_url' => $redir,
            'correlation_id' => $correlation,
            'sessionid' => $id,
        ];
    }

    /**
     * Validate return state and mark admitted. Does not create attempts.
     *
     * @return array{cmid:int, sessionid:int}
     */
    public function complete_return(int $userid, string $statetoken): array {
        $data = state_token::verify($statetoken);
        if ($data === null) {
            throw new \moodle_exception('proctopus_invalid_return', 'local_proctopus');
        }
        if ((int) $data['uid'] !== $userid) {
            throw new \moodle_exception('proctopus_invalid_return', 'local_proctopus');
        }

        $session = $this->sessions()->get_by_id((int) $data['sid']);
        if (!$session) {
            throw new \moodle_exception('proctopus_invalid_return', 'local_proctopus');
        }
        if ((int) $session->userid !== $userid || (int) $session->cmid !== (int) $data['cmid']) {
            throw new \moodle_exception('proctopus_invalid_return', 'local_proctopus');
        }
        if ((string) $session->external_session_id !== (string) $data['ext']) {
            throw new \moodle_exception('proctopus_invalid_return', 'local_proctopus');
        }
        if (in_array($session->status, [
            'failed', 'completed', 'completed_pending_result', 'expired', 'closed', 'superseded',
        ], true)) {
            throw new \moodle_exception('proctopus_invalid_return', 'local_proctopus');
        }

        // Replay: admit once.
        if ($session->status !== 'admitted') {
            $this->sessions()->update_status((int) $session->id, 'admitted', [
                'result_summary' => 'admitted',
            ]);
        }

        return [
            'cmid' => (int) $session->cmid,
            'sessionid' => (int) $session->id,
        ];
    }
}
