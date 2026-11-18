<?php
namespace local_kaznu\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Safe Univer login attempt (no password / cookies / query / PII dump).
 */
class univer_login_attempted extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function create_safe(string $correlationid, string $outcome, ?int $userid = null): self {
        $data = [
            'context' => \context_system::instance(),
            'other' => [
                'correlationid' => $correlationid,
                'outcome' => $outcome,
            ],
        ];
        if ($userid) {
            $data['userid'] = $userid;
            $data['relateduserid'] = $userid;
        }
        return self::create($data);
    }

    public function get_description(): string {
        $cid = $this->other['correlationid'] ?? '';
        $outcome = $this->other['outcome'] ?? '';
        return "Univer login attempt correlation={$cid} outcome={$outcome}";
    }

    public static function get_name(): string {
        return get_string('event_univer_login_attempted', 'local_kaznu');
    }
}
