<?php
namespace local_kaznu\event;

defined('MOODLE_INTERNAL') || die();

class student_assessments_synced extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function create_safe(int $userid, int $count): self {
        return self::create([
            'context' => \context_system::instance(),
            'userid' => $userid,
            'relateduserid' => $userid,
            'other' => ['count' => $count],
        ]);
    }

    public static function get_name(): string {
        return get_string('event_student_assessments_synced', 'local_kaznu');
    }

    public function get_description(): string {
        return "Student assessments synced uid={$this->relateduserid} count={$this->other['count']}";
    }
}
