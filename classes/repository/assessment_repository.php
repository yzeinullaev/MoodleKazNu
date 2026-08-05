<?php
namespace local_kaznu\repository;

use local_kaznu\api\dto\assessment_item;

defined('MOODLE_INTERNAL') || die();

/**
 * Atomic snapshot for attestation / GPA rows (not Moodle gradebook).
 */
class assessment_repository {
    /**
     * @param assessment_item[] $items
     */
    public function replace_snapshot(int $userid, int $year, int $semester, array $items): int {
        global $DB;
        $now = time();
        $tx = $DB->start_delegated_transaction();
        $DB->delete_records('local_kaznu_assessment', [
            'userid' => $userid,
            'academyear' => $year,
            'academsemester' => $semester,
        ]);
        $n = 0;
        $seen = [];
        foreach ($items as $item) {
            if (!$item instanceof assessment_item) {
                continue;
            }
            if (isset($seen[$item->external_key()])) {
                continue;
            }
            $seen[$item->external_key()] = true;
            $DB->insert_record('local_kaznu_assessment', (object) [
                'userid' => $userid,
                'academyear' => $year,
                'academsemester' => $semester,
                'external_key' => $item->external_key(),
                'subjectname' => $item->subjectname,
                'credit' => $item->credit,
                'resultrk1' => $item->resultrk1,
                'resultrk2' => $item->resultrk2,
                'result' => $item->result,
                'totalres' => $item->totalres,
                'gpa' => $item->gpa,
                'symbol' => $item->symbol,
                'lettertext' => $item->lettertext,
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
        $sql = "SELECT * FROM {local_kaznu_assessment} WHERE userid = :userid";
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
        $DB->delete_records('local_kaznu_assessment', ['userid' => $userid]);
    }

    public function purge_older_than(int $beforets): int {
        global $DB;
        $sql = "lastsynced < :t AND NOT EXISTS (
            SELECT 1 FROM {local_kaznu_univer_user} u
             WHERE u.userid = {local_kaznu_assessment}.userid
               AND u.current_year = {local_kaznu_assessment}.academyear
               AND u.current_semester = {local_kaznu_assessment}.academsemester
        )";
        return (int) $DB->delete_records_select('local_kaznu_assessment', $sql, ['t' => $beforets]);
    }
}
