<?php
namespace local_kaznu\repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Persistence for local_kaznu_univer_user.
 */
class univer_user_repository {
    /**
     * @return \stdClass|false
     */
    public function get_by_univer_userid(int $univeruserid) {
        global $DB;
        return $DB->get_record('local_kaznu_univer_user', ['univer_userid' => $univeruserid]);
    }

    /**
     * @return \stdClass|false
     */
    public function get_by_moodle_userid(int $userid) {
        global $DB;
        return $DB->get_record('local_kaznu_univer_user', ['userid' => $userid]);
    }

    public function insert(\stdClass $record): int {
        global $DB;
        $now = time();
        $record->timecreated = $record->timecreated ?? $now;
        $record->timemodified = $now;
        return (int) $DB->insert_record('local_kaznu_univer_user', $record);
    }

    public function update(\stdClass $record): void {
        global $DB;
        $record->timemodified = time();
        $DB->update_record('local_kaznu_univer_user', $record);
    }

    public function delete_for_userid(int $userid): void {
        global $DB;
        $DB->delete_records('local_kaznu_univer_user', ['userid' => $userid]);
    }
}
