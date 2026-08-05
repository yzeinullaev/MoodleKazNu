<?php
namespace local_kaznu\event;

defined('MOODLE_INTERNAL') || die();

class student_data_sync_failed extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function create_safe(int $userid, string $module, string $errorcode): self {
        return self::create([
            'context' => \context_system::instance(),
            'userid' => $userid,
            'relateduserid' => $userid,
            'other' => [
                'module' => substr($module, 0, 40),
                'errorcode' => substr($errorcode, 0, 40),
            ],
        ]);
    }

    public static function get_name(): string {
        return get_string('event_student_data_sync_failed', 'local_kaznu');
    }

    public function get_description(): string {
        $o = $this->other;
        return "Student data sync failed uid={$this->relateduserid} module={$o['module']} code={$o['errorcode']}";
    }
}
