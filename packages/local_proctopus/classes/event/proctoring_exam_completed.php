<?php
// This file is part of Moodle - http://moodle.org/

namespace local_proctopus\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Safe event: proctoring exam completed (local session closed). No JWT/PII.
 */
class proctoring_exam_completed extends \core\event\base {

    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_proctopus_session';
    }

    public static function get_name(): string {
        return get_string('event_proctoring_exam_completed', 'local_proctopus');
    }

    public function get_description(): string {
        return 'Proctopus local session ' . $this->objectid . ' completed for user ' . $this->userid
            . ' (cmid ' . $this->contextinstanceid . ').';
    }

    public function get_url(): ?\moodle_url {
        return new \moodle_url('/local/proctopus/complete.php', ['cmid' => $this->contextinstanceid]);
    }

    protected function validate_data(): void {
        parent::validate_data();
    }

    public static function get_objectid_mapping(): array {
        return ['db' => 'local_proctopus_session', 'restore' => \core\event\base::NOT_MAPPED];
    }
}
