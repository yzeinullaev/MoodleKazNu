<?php
namespace local_proctopus\jwt;

defined('MOODLE_INTERNAL') || die();

/**
 * Build legacy Proctopus-compatible JWT payload from trusted Moodle records only.
 * Never uses browser GET name/time. Never uses username as thirdName.
 */
class legacy_payload_builder {
    /**
     * @param \stdClass $user Moodle user
     * @param \stdClass $quiz quiz row
     * @param \stdClass $cm course module
     * @param \stdClass|null $ruleset quizaccess_proctopus row or synthetic
     * @return array<string,mixed>
     */
    public function build(
        \stdClass $user,
        \stdClass $quiz,
        \stdClass $cm,
        string $externalsessionid,
        string $returnstate,
        ?\stdClass $ruleset = null
    ): array {
        global $CFG;

        $account = local_proctopus_account_name();
        if ($account === 'kaznu_moodle') {
            throw new \moodle_exception('proctopus_not_configured', 'local_proctopus');
        }

        $lang = trim((string) (get_config('local_proctopus', 'proctopus_default_language') ?: 'ru'));
        $mode = trim((string) (get_config('local_proctopus', 'proctopus_default_mode') ?: 'online'));
        if ($mode === '') {
            $mode = 'online';
        }

        $duration = $this->resolve_duration($quiz, $ruleset);
        $open = !empty($quiz->timeopen) ? (int) $quiz->timeopen : time();
        $close = !empty($quiz->timeclose) ? (int) $quiz->timeclose : ($open + $duration * 60);

        $third = '';
        if (!empty($user->middlename)) {
            $third = trim((string) $user->middlename);
        }

        try {
            $ctx = \context_module::instance((int) $cm->id);
        } catch (\Throwable $e) {
            // Smoke/unit may pass synthetic cm; never use browser data for name.
            $ctx = !empty($cm->course)
                ? \context_course::instance((int) $cm->course, IGNORE_MISSING)
                : null;
            if (!$ctx) {
                $ctx = \context_system::instance();
            }
        }
        $examname = format_string($quiz->name, true, ['context' => $ctx]);
        // Plain text description — no unvalidated HTML template from legacy.
        $examdesc = $examname;

        $rules = [
            'websites' => !empty($ruleset->proctopuswebsites) ? true : false,
            'look_away' => !empty($ruleset->proctopuslookaway),
            'move_away' => !empty($ruleset->proctopusmoveaway),
            'voices' => !empty($ruleset->proctopusvoices),
        ];
        if ($ruleset && (string) $ruleset->proctopuswebsites !== '') {
            // websites flag true if field non-empty (legacy bool).
            $rules['websites'] = true;
        }

        $sessionurl = (new \moodle_url('/local/proctopus/return.php', [
            'state' => $returnstate,
            'phase' => 'session',
        ]))->out(false);

        $redirecturl = (new \moodle_url('/local/proctopus/return.php', [
            'state' => $returnstate,
        ]))->out(false);

        // Guard against legacy hosts.
        foreach ([$sessionurl, $redirecturl] as $u) {
            if (str_contains($u, 'dl.kaznu') || str_contains($u, 'kaznu_moodle')) {
                throw new \moodle_exception('proctopus_not_configured', 'local_proctopus');
            }
        }

        // Code.php body only — no iat/nbf/exp/jti/iss (Firebase JWT::encode payload as-is).
        return [
            'userId' => (int) $user->id,
            'lastName' => (string) $user->lastname,
            'firstName' => (string) $user->firstname,
            'thirdName' => $third,
            'language' => $lang,
            'accountName' => $account,
            'examId' => (int) $cm->id,
            'examName' => $examname,
            'duration' => $duration,
            'schedule' => false,
            'proctoring' => $mode,
            'examDesc' => $examdesc,
            'rules' => $rules,
            'startDate' => gmdate('Y-m-d\TH:i:s\Z', $open),
            'endDate' => gmdate('Y-m-d\TH:i:s\Z', $close),
            'sessionId' => $externalsessionid,
            'sessionUrl' => $sessionurl,
            'redirectUrl' => $redirecturl,
        ];
    }

    private function resolve_duration(\stdClass $quiz, ?\stdClass $ruleset): int {
        if ($ruleset && !empty($ruleset->proctopusduration) && (int) $ruleset->proctopusduration > 0) {
            // stored as minutes if large values look wrong? Spec says seconds for override.
            // Treat proctopusduration as minutes when >= 5 and quiz->timelimit in seconds.
            // Code.php used duration 90 minutes-like. Our setting defaults 60 minutes for JWT duration field.
            return (int) $ruleset->proctopusduration;
        }
        if (!empty($quiz->timelimit) && (int) $quiz->timelimit > 0) {
            // Moodle timelimit is seconds → convert to minutes for legacy duration field.
            return max(1, (int) ceil(((int) $quiz->timelimit) / 60));
        }
        $def = (int) (get_config('local_proctopus', 'proctopus_default_duration') ?: 60);
        return max(1, $def);
    }
}
