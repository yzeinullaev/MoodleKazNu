<?php
namespace local_kaznu\event;

defined('MOODLE_INTERNAL') || die();

class student_course_not_found extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function create_safe(int $userid, int $subjectid, string $idnumber): self {
        return self::create([
            'context' => \context_system::instance(),
            'userid' => $userid,
            'relateduserid' => $userid,
            'other' => [
                'subjectid' => $subjectid,
                'idnumber' => $idnumber,
            ],
        ]);
    }

    public static function get_name(): string {
        return get_string('event_student_course_not_found', 'local_kaznu');
    }

    public function get_description(): string {
        return "Moodle course not found for Univer subject uid={$this->relateduserid} "
            . "subject={$this->other['subjectid']} idnumber={$this->other['idnumber']}";
    }
}
