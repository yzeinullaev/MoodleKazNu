<?php
namespace local_proctopus\event;

defined('MOODLE_INTERNAL') || die();

class proctoring_callback_received extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_proctopus_session';
    }
    public static function get_name(): string {
        return get_string('event_callback_received', 'local_proctopus');
    }
    public function get_description(): string {
        $cid = $this->other['correlation_id'] ?? '';
        return 'Proctopus callback_received correlation=' . $cid;
    }
}
