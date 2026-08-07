<?php
namespace local_proctopus\event;

defined('MOODLE_INTERNAL') || die();

class proctoring_session_started extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_proctopus_session';
    }
    public static function get_name(): string {
        return get_string('event_session_started', 'local_proctopus');
    }
    public function get_description(): string {
        $cid = $this->other['correlation_id'] ?? '';
        return 'Proctopus session_started correlation=' . $cid;
    }
}
