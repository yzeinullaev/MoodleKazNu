<?php
namespace local_proctopus\callback;

defined('MOODLE_INTERNAL') || die();

/**
 * Uses unique external_event_id in local_proctopus_callback.
 */
class db_replay_guard implements replay_guard_interface {
    public function accept(string $externaleventid, string $payloadhash): bool {
        global $DB;
        if ($externaleventid === '') {
            return false;
        }
        if ($DB->record_exists('local_proctopus_callback', ['external_event_id' => $externaleventid])) {
            return false;
        }
        return true;
    }
}
