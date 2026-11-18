<?php
namespace local_kaznu\event;

defined('MOODLE_INTERNAL') || die();

class student_schedule_synced extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function create_safe(int $userid, int $lessons, int $exams, int $midterms): self {
        return self::create([
            'context' => \context_system::instance(),
            'userid' => $userid,
            'relateduserid' => $userid,
            'other' => [
                'lessons' => $lessons,
                'exams' => $exams,
                'midterms' => $midterms,
            ],
        ]);
    }

    public static function get_name(): string {
        return get_string('event_student_schedule_synced', 'local_kaznu');
    }

    public function get_description(): string {
        $o = $this->other;
        return "Student schedule synced uid={$this->relateduserid} L={$o['lessons']} E={$o['exams']} M={$o['midterms']}";
    }
}
