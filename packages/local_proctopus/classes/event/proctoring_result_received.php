<?php
// This file is part of Moodle - http://moodle.org/

namespace local_proctopus\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Safe event: provider callback result applied. No raw body / JWT.
 */
class proctoring_result_received extends \core\event\base {

    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_proctopus_session';
    }

    public static function get_name(): string {
        return get_string('event_proctoring_result_received', 'local_proctopus');
    }

    public function get_description(): string {
        return 'Proctopus provider result received for session ' . $this->objectid
            . ' (user ' . $this->userid . ').';
    }

    public function get_url(): ?\moodle_url {
        return new \moodle_url('/local/proctopus/complete.php', ['cmid' => $this->contextinstanceid]);
    }

    public static function get_objectid_mapping(): array {
        return ['db' => 'local_proctopus_session', 'restore' => \core\event\base::NOT_MAPPED];
    }
}
