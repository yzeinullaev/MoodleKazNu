<?php
// This file is part of Moodle - http://moodle.org/
//
// Proctopus local session reconciliation (Moodle is source of truth for attempt end).

namespace local_proctopus\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Reconcile local open sessions against quiz_attempts without calling Proctopus API.
 */
final class proctoring_session_reconciler {

    /** Statuses that mean "still open" for UI / reuse. */
    public const OPEN_STATUSES = ['created', 'started', 'redirected', 'admitted', 'active'];

    /**
     * @return string[]
     */
    public static function open_status_list(): array {
        return self::OPEN_STATUSES;
    }

    /**
     * Close sessions when Moodle quiz attempt is finished.
     *
     * @return array{completed:int, closed:int, remaining_open:int}
     */
    public static function on_attempt_finished(int $userid, int $quizid, int $cmid, int $attemptid): array {
        global $DB;

        $now = time();
        $completed = 0;
        $closed = 0;

        $opens = $DB->get_records_select(
            'local_proctopus_session',
            "userid = :userid AND quizid = :quizid AND cmid = :cmid
             AND status IN ('created','started','redirected','admitted','active')",
            ['userid' => $userid, 'quizid' => $quizid, 'cmid' => $cmid],
            'id DESC'
        );

        $candidates = [];
        foreach ($opens as $session) {
            $linked = (int) ($session->attemptid ?? 0);
            if ($linked === $attemptid || $linked === 0) {
                $candidates[] = $session;
                continue;
            }
            $state = self::attempt_state($linked);
            if ($state === 'inprogress') {
                continue;
            }
            self::mark_terminal($session, 'superseded', $now, 'superseded', $linked);
            $closed++;
        }

        $primary = true;
        foreach ($candidates as $session) {
            if ($primary) {
                self::mark_completed($session, $attemptid, $now, 'quiz_finished');
                $completed++;
                $primary = false;
            } else {
                self::mark_terminal($session, 'superseded', $now, 'superseded', $attemptid);
                $closed++;
            }
        }

        $remaining = self::count_open($userid, $cmid);
        if ($remaining > 0) {
            $closed += self::force_close_non_inprogress($userid, $cmid, $attemptid, $now);
            $remaining = self::count_open($userid, $cmid);
        }

        self::clear_browser_admit($userid, $cmid);

        return [
            'completed' => $completed,
            'closed' => $closed,
            'remaining_open' => $remaining,
        ];
    }

    /**
     * Link open admitted session to a newly started attempt.
     */
    public static function on_attempt_started(int $userid, int $quizid, int $cmid, int $attemptid): void {
        global $DB;

        $rows = $DB->get_records_select(
            'local_proctopus_session',
            "userid = :userid AND quizid = :quizid AND cmid = :cmid AND status = 'admitted'
             AND (attemptid IS NULL OR attemptid = 0 OR attemptid = :aid)",
            ['userid' => $userid, 'quizid' => $quizid, 'cmid' => $cmid, 'aid' => $attemptid],
            'id DESC',
            '*',
            0,
            1
        );
        $session = $rows ? reset($rows) : null;
        if (!$session) {
            return;
        }
        if ((int) ($session->attemptid ?? 0) === $attemptid) {
            return;
        }
        $session->attemptid = $attemptid;
        $session->timemodified = time();
        $DB->update_record('local_proctopus_session', $session);
    }

    /**
     * Reconcile open sessions (optional filters). Does not call external API.
     *
     * @return list<array{id:int,userid:int,cmid:int,attemptid:int|null,status:string,proposed:string,reason:string,attempt_state:string,applied:bool}>
     */
    public static function reconcile(?int $userid = null, ?int $cmid = null, ?int $olderthan = null, bool $apply = false): array {
        global $DB;

        if (!$DB->get_manager()->table_exists('local_proctopus_session')) {
            return [];
        }

        $where = ["status IN ('created','started','redirected','admitted','active')"];
        $params = [];
        if ($userid) {
            $where[] = 'userid = :userid';
            $params['userid'] = $userid;
        }
        if ($cmid) {
            $where[] = 'cmid = :cmid';
            $params['cmid'] = $cmid;
        }
        if ($olderthan) {
            $where[] = 'timemodified < :older';
            $params['older'] = time() - $olderthan;
        }

        $rows = $DB->get_records_select('local_proctopus_session', implode(' AND ', $where), $params, 'id ASC');
        $plan = [];
        $now = time();

        foreach ($rows as $session) {
            $decision = self::decide_for_session($session, $now);
            $applied = false;
            if ($apply && $decision['proposed'] !== $session->status) {
                if ($decision['proposed'] === 'completed' || $decision['proposed'] === 'completed_pending_result') {
                    self::mark_completed(
                        $session,
                        (int) ($decision['attemptid'] ?? $session->attemptid ?? 0),
                        $now,
                        $decision['reason']
                    );
                } else {
                    self::mark_terminal(
                        $session,
                        $decision['proposed'],
                        $now,
                        $decision['reason'],
                        (int) ($session->attemptid ?? 0)
                    );
                }
                $applied = true;
            }
            $plan[] = [
                'id' => (int) $session->id,
                'userid' => (int) $session->userid,
                'cmid' => (int) $session->cmid,
                'attemptid' => $session->attemptid !== null ? (int) $session->attemptid : null,
                'status' => (string) $session->status,
                'proposed' => $decision['proposed'],
                'reason' => $decision['reason'],
                'attempt_state' => $decision['attempt_state'],
                'applied' => $applied,
            ];
        }

        return $plan;
    }

    /**
     * Before reuse / access: close finished-linked opens for this context.
     */
    public static function reconcile_user_cm(int $userid, int $cmid): void {
        self::reconcile($userid, $cmid, null, true);
    }

    /**
     * Count open sessions for user+cmid.
     */
    public static function count_open(int $userid, int $cmid): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_proctopus_session')) {
            return 0;
        }
        return (int) $DB->count_records_select(
            'local_proctopus_session',
            "userid = :u AND cmid = :c AND status IN ('created','started','redirected','admitted','active')",
            ['u' => $userid, 'c' => $cmid]
        );
    }

    /**
     * Provider callback after finish: upgrade pending → completed with safe result.
     */
    public static function apply_provider_result(object $session, ?string $safecode, ?string $safesummary): void {
        global $DB;

        $now = time();
        $session = $DB->get_record('local_proctopus_session', ['id' => $session->id], '*', MUST_EXIST);

        if (in_array($session->status, self::OPEN_STATUSES, true)
                || $session->status === 'completed_pending_result') {
            // Do not flip Moodle attempt; only local status.
            if (in_array($session->status, self::OPEN_STATUSES, true)) {
                // Callback arrived before Moodle finish: store result, leave open until Moodle finish OR mark noted.
                // Spec B: save provider status; do not auto-finish Moodle.
                // Keep open but record result fields; status stays open until Moodle finish.
                if ($safecode !== null && $safecode !== '') {
                    $session->result_code = substr($safecode, 0, 64);
                }
                if ($safesummary !== null && $safesummary !== '') {
                    $session->result_summary = substr($safesummary, 0, 255);
                }
                $session->last_callback_at = $now;
                $session->timemodified = $now;
                $DB->update_record('local_proctopus_session', $session);
                self::trigger_result_event((int) $session->id, (int) $session->userid, (int) $session->cmid);
                return;
            }
            $session->status = 'completed';
            if (empty($session->completed_at)) {
                $session->completed_at = $now;
            }
        }

        if ($safecode !== null && $safecode !== '') {
            $session->result_code = substr($safecode, 0, 64);
        }
        if ($safesummary !== null && $safesummary !== '') {
            $session->result_summary = substr($safesummary, 0, 255);
        }
        $session->last_callback_at = $now;
        $session->timemodified = $now;
        // Already completed: still update result safely (idempotent).
        if ($session->status === 'completed' || $session->status === 'completed_pending_result') {
            $session->status = 'completed';
        }
        $DB->update_record('local_proctopus_session', $session);
        self::trigger_result_event((int) $session->id, (int) $session->userid, (int) $session->cmid);
    }

    /**
     * Whether student currently has an open proctoring session for UI.
     */
    public static function has_open_session(int $userid, int $cmid): bool {
        return self::count_open($userid, $cmid) > 0;
    }

    /**
     * @return array{proposed:string,reason:string,attempt_state:string,attemptid:?int}
     */
    private static function decide_for_session(object $session, int $now): array {
        global $DB;

        $aid = (int) ($session->attemptid ?? 0);
        $expires = (int) ($session->expires_at ?? 0);

        if ($expires > 0 && $expires < $now) {
            return [
                'proposed' => 'expired',
                'reason' => 'ttl_expired',
                'attempt_state' => $aid ? self::attempt_state($aid) : 'none',
                'attemptid' => $aid ?: null,
            ];
        }

        if ($aid > 0) {
            $state = self::attempt_state($aid);
            if ($state === 'finished') {
                return [
                    'proposed' => 'completed',
                    'reason' => 'quiz_finished',
                    'attempt_state' => $state,
                    'attemptid' => $aid,
                ];
            }
            if (in_array($state, ['abandoned', 'overdue'], true)) {
                return [
                    'proposed' => 'expired',
                    'reason' => 'attempt_' . $state,
                    'attempt_state' => $state,
                    'attemptid' => $aid,
                ];
            }
            if ($state === 'missing') {
                return [
                    'proposed' => 'expired',
                    'reason' => 'attempt_missing',
                    'attempt_state' => $state,
                    'attemptid' => $aid,
                ];
            }
            return [
                'proposed' => $session->status,
                'reason' => 'attempt_inprogress',
                'attempt_state' => $state,
                'attemptid' => $aid,
            ];
        }

        // No attempt linked: check latest attempt for this user+quiz.
        $attempts = $DB->get_records_sql(
            "SELECT id, state FROM {quiz_attempts} WHERE quiz = ? AND userid = ? ORDER BY id DESC",
            [(int) $session->quizid, (int) $session->userid],
            0,
            1
        );
        $attempt = $attempts ? reset($attempts) : null;
        if ($attempt) {
            if ($attempt->state === 'finished') {
                return [
                    'proposed' => 'completed',
                    'reason' => 'quiz_finished',
                    'attempt_state' => 'finished',
                    'attemptid' => (int) $attempt->id,
                ];
            }
            if ($attempt->state === 'inprogress') {
                // May still be admitted pre-link — keep if recent.
                return [
                    'proposed' => $session->status,
                    'reason' => 'attempt_inprogress_unlinked',
                    'attempt_state' => 'inprogress',
                    'attemptid' => (int) $attempt->id,
                ];
            }
            return [
                'proposed' => 'expired',
                'reason' => 'attempt_' . $attempt->state,
                'attempt_state' => $attempt->state,
                'attemptid' => (int) $attempt->id,
            ];
        }

        // No attempt: expire if stale (30 min), else keep for admission window.
        if ((int) $session->timemodified < ($now - 1800)) {
            return [
                'proposed' => 'expired',
                'reason' => 'attempt_missing',
                'attempt_state' => 'none',
                'attemptid' => null,
            ];
        }

        return [
            'proposed' => $session->status,
            'reason' => 'recent_no_attempt',
            'attempt_state' => 'none',
            'attemptid' => null,
        ];
    }

    private static function attempt_state(int $attemptid): string {
        global $DB;
        $a = $DB->get_record('quiz_attempts', ['id' => $attemptid], 'state', IGNORE_MISSING);
        return $a ? (string) $a->state : 'missing';
    }

    private static function mark_completed(object $session, int $attemptid, int $now, string $reason): void {
        global $DB;

        // Idempotent terminal.
        if (in_array($session->status, ['completed', 'completed_pending_result'], true)
                && (int) ($session->attemptid ?? 0) === $attemptid
                && !empty($session->completed_at)) {
            return;
        }

        $code = trim((string) ($session->result_code ?? ''));
        $sum = trim((string) ($session->result_summary ?? ''));
        $hasresult = ($code !== '') || ($sum !== '' && !in_array($sum, ['admitted', 'redirected'], true));
        $hascallback = $DB->record_exists('local_proctopus_callback', ['sessionid' => $session->id]);

        if ($hascallback || $hasresult) {
            $session->status = 'completed';
        } else {
            $session->status = 'completed_pending_result';
        }

        if ($attemptid > 0) {
            $session->attemptid = $attemptid;
        }
        if (empty($session->completed_at)) {
            $session->completed_at = $now;
        }
        if ($session->result_summary === null || $session->result_summary === ''
                || in_array($session->result_summary, ['admitted', 'redirected'], true)) {
            $session->result_summary = substr('moodle_' . $reason, 0, 255);
        }
        $session->timemodified = $now;
        $DB->update_record('local_proctopus_session', $session);

        self::trigger_completed_event(
            (int) $session->id,
            (int) $session->userid,
            (int) $session->cmid,
            $attemptid
        );
    }

    private static function mark_terminal(object $session, string $status, int $now, string $reason, int $attemptid = 0): void {
        global $DB;

        if ($session->status === $status
                && in_array($status, ['expired', 'closed', 'superseded', 'failed'], true)) {
            return;
        }
        $session->status = $status;
        if ($attemptid > 0 && empty($session->attemptid)) {
            $session->attemptid = $attemptid;
        }
        $session->result_summary = substr($reason, 0, 255);
        $session->timemodified = $now;
        $DB->update_record('local_proctopus_session', $session);
    }

    private static function force_close_non_inprogress(int $userid, int $cmid, int $attemptid, int $now): int {
        global $DB;
        $n = 0;
        $opens = $DB->get_records_select(
            'local_proctopus_session',
            "userid = :u AND cmid = :c AND status IN ('created','started','redirected','admitted','active')",
            ['u' => $userid, 'c' => $cmid]
        );
        foreach ($opens as $session) {
            $aid = (int) ($session->attemptid ?? 0);
            if ($aid > 0 && self::attempt_state($aid) === 'inprogress') {
                continue;
            }
            if ($aid === $attemptid || $aid === 0) {
                self::mark_completed($session, $attemptid, $now, 'quiz_finished');
            } else {
                self::mark_terminal($session, 'superseded', $now, 'superseded', $aid);
            }
            $n++;
        }
        return $n;
    }

    private static function clear_browser_admit(int $userid, int $cmid): void {
        global $SESSION, $USER;
        if (empty($USER->id) || (int) $USER->id !== $userid) {
            return;
        }
        if (!empty($SESSION->local_proctopus_admit) && is_array($SESSION->local_proctopus_admit)) {
            unset($SESSION->local_proctopus_admit[$cmid]);
        }
    }

    private static function trigger_completed_event(int $sessionid, int $userid, int $cmid, int $attemptid): void {
        try {
            $event = \local_proctopus\event\proctoring_exam_completed::create([
                'context' => \context_module::instance($cmid),
                'objectid' => $sessionid,
                'userid' => $userid,
                'relateduserid' => $userid,
                'other' => [
                    'attemptid' => $attemptid,
                ],
            ]);
            $event->trigger();
        } catch (\Throwable $e) {
            debugging('proctoring_exam_completed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    private static function trigger_result_event(int $sessionid, int $userid, int $cmid): void {
        try {
            $event = \local_proctopus\event\proctoring_result_received::create([
                'context' => \context_module::instance($cmid),
                'objectid' => $sessionid,
                'userid' => $userid,
                'relateduserid' => $userid,
                'other' => [],
            ]);
            $event->trigger();
        } catch (\Throwable $e) {
            debugging('proctoring_result_received: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
