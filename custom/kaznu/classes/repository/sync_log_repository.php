<?php
namespace local_kaznu\repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Safe technical sync log (no passwords, no full API dumps, no IIN).
 */
class sync_log_repository {
    public function add(
        int $userid,
        string $action,
        string $result,
        int $subjectid = 0,
        int $courseid = 0,
        string $detail = ''
    ): void {
        global $DB;
        $DB->insert_record('local_kaznu_sync_log', (object) [
            'userid' => $userid,
            'action' => substr($action, 0, 40),
            'result' => substr($result, 0, 40),
            'subjectid' => $subjectid,
            'courseid' => $courseid,
            'detail' => substr($detail, 0, 255),
            'timecreated' => time(),
        ]);
    }

    public function delete_for_userid(int $userid): void {
        global $DB;
        $DB->delete_records('local_kaznu_sync_log', ['userid' => $userid]);
    }
}
