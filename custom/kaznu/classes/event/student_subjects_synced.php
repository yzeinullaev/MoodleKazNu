<?php
namespace local_kaznu\event;

defined('MOODLE_INTERNAL') || die();

class student_subjects_synced extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function create_safe(
        int $userid,
        int $upserted,
        int $staled,
        int $reactivated,
        int $enrolled
    ): self {
        return self::create([
            'context' => \context_system::instance(),
            'userid' => $userid,
            'relateduserid' => $userid,
            'other' => [
                'upserted' => $upserted,
                'staled' => $staled,
                'reactivated' => $reactivated,
                'enrolled' => $enrolled,
            ],
        ]);
    }

    public static function get_name(): string {
        return get_string('event_student_subjects_synced', 'local_kaznu');
    }

    public function get_description(): string {
        $o = $this->other;
        return "Student subjects synced uid={$this->relateduserid} "
            . "upserted={$o['upserted']} staled={$o['staled']} enrolled={$o['enrolled']}";
    }
}
