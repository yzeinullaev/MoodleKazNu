<?php
namespace local_kaznu\repository;

use local_kaznu\api\dto\attendance_item;

defined('MOODLE_INTERNAL') || die();

/**
 * Atomic snapshot for attendance rates from Univer.
 */
class attendance_repository {
    /**
     * @param attendance_item[] $items
     */
    public function replace_snapshot(int $userid, int $year, int $semester, array $items): int {
        global $DB;
        $now = time();
        $tx = $DB->start_delegated_transaction();
        $DB->delete_records('local_kaznu_attendance', [
            'userid' => $userid,
            'academyear' => $year,
            'academsemester' => $semester,
        ]);
        $n = 0;
        $seen = [];
        foreach ($items as $item) {
            if (!$item instanceof attendance_item) {
                continue;
            }
            if (isset($seen[$item->external_key()])) {
                continue;
            }
            $seen[$item->external_key()] = true;
            $DB->insert_record('local_kaznu_attendance', (object) [
                'userid' => $userid,
                'academyear' => $year,
                'academsemester' => $semester,
                'external_key' => $item->external_key(),
                'subjectname' => $item->subjectname,
                'label' => $item->label,
                'period' => $item->period,
                'groupinfo' => $item->groupinfo,
                'rate1' => $item->rate1,
                'rate2' => $item->rate2,
                'lastsynced' => $now,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $n++;
        }
        $tx->allow_commit();
        return $n;
    }

    /**
     * @return \stdClass[]
     */
    public function get_for_user(int $userid, int $year = 0, int $semester = 0): array {
        global $DB;
        $params = ['userid' => $userid];
        $sql = "SELECT * FROM {local_kaznu_attendance} WHERE userid = :userid";
        if ($year > 0) {
            $sql .= " AND academyear = :y";
            $params['y'] = $year;
        }
        if ($semester > 0) {
            $sql .= " AND academsemester = :s";
            $params['s'] = $semester;
        }
        $sql .= " ORDER BY subjectname ASC";
        return array_values($DB->get_records_sql($sql, $params));
    }

    public function delete_for_userid(int $userid): void {
        global $DB;
        $DB->delete_records('local_kaznu_attendance', ['userid' => $userid]);
    }

    public function purge_older_than(int $beforets): int {
        global $DB;
        $sql = "lastsynced < :t AND NOT EXISTS (
            SELECT 1 FROM {local_kaznu_univer_user} u
             WHERE u.userid = {local_kaznu_attendance}.userid
               AND u.current_year = {local_kaznu_attendance}.academyear
               AND u.current_semester = {local_kaznu_attendance}.academsemester
        )";
        return (int) $DB->delete_records_select('local_kaznu_attendance', $sql, ['t' => $beforets]);
    }
}
