<?php
namespace local_kaznu\event;

defined('MOODLE_INTERNAL') || die();

class student_enrolled extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'course';
    }

    public static function create_safe(int $userid, int $courseid, int $subjectid): self {
        return self::create([
            'context' => \context_course::instance($courseid),
            'userid' => $userid,
            'relateduserid' => $userid,
            'objectid' => $courseid,
            'courseid' => $courseid,
            'other' => ['subjectid' => $subjectid],
        ]);
    }

    public static function get_name(): string {
        return get_string('event_student_enrolled', 'local_kaznu');
    }

    public function get_description(): string {
        return "Student enrolled via Univer uid={$this->relateduserid} course={$this->objectid} subject={$this->other['subjectid']}";
    }
}
