<?php
namespace local_kaznu\event;

defined('MOODLE_INTERNAL') || die();

class student_subject_marked_stale extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function create_safe(int $userid, int $subjectid, int $year, int $semester): self {
        return self::create([
            'context' => \context_system::instance(),
            'userid' => $userid,
            'relateduserid' => $userid,
            'other' => [
                'subjectid' => $subjectid,
                'year' => $year,
                'semester' => $semester,
            ],
        ]);
    }

    public static function get_name(): string {
        return get_string('event_student_subject_marked_stale', 'local_kaznu');
    }

    public function get_description(): string {
        $o = $this->other;
        return "Subject marked stale uid={$this->relateduserid} subject={$o['subjectid']} period={$o['year']}/{$o['semester']}";
    }
}
