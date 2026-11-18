<?php
namespace local_kaznu\repository;

use local_kaznu\api\dto\exam_item;
use local_kaznu\api\dto\midterm_item;
use local_kaznu\api\dto\schedule_item;
use local_kaznu\cache_module;
use local_kaznu\local\univer_datetime;

defined('MOODLE_INTERNAL') || die();

/**
 * Atomic snapshot cache for lessons / exams / midterms.
 */
class schedule_repository {
    /**
     * Replace period snapshot for one type (atomic).
     *
     * @param schedule_item[]|exam_item[]|midterm_item[] $items
     */
    public function replace_snapshot(int $userid, string $type, int $year, int $semester, array $items): int {
        global $DB;
        $now = time();
        $tx = $DB->start_delegated_transaction();
        $DB->delete_records('local_kaznu_schedule', [
            'userid' => $userid,
            'itemtype' => $type,
            'academyear' => $year,
            'academsemester' => $semester,
        ]);
        $n = 0;
        $seen = [];
        foreach ($items as $item) {
            $rec = $this->map_item($userid, $type, $year, $semester, $item, $now);
            if ($rec === null) {
                continue;
            }
            if (isset($seen[$rec->external_key])) {
                continue;
            }
            $seen[$rec->external_key] = true;
            $DB->insert_record('local_kaznu_schedule', $rec);
            $n++;
        }
        $tx->allow_commit();
        return $n;
    }

    /**
     * @return \stdClass[]
     */
    public function get_for_user(int $userid, string $type, int $year = 0, int $semester = 0): array {
        global $DB;
        $params = ['userid' => $userid, 'itemtype' => $type];
        $sql = "SELECT * FROM {local_kaznu_schedule} WHERE userid = :userid AND itemtype = :itemtype";
        if ($year > 0) {
            $sql .= " AND academyear = :y";
            $params['y'] = $year;
        }
        if ($semester > 0) {
            $sql .= " AND academsemester = :s";
            $params['s'] = $semester;
        }
        $sql .= " ORDER BY eventdate ASC, timebegin ASC, subjectname ASC";
        return array_values($DB->get_records_sql($sql, $params));
    }

    public function delete_for_userid(int $userid): void {
        global $DB;
        $DB->delete_records('local_kaznu_schedule', ['userid' => $userid]);
    }

    public function purge_older_than(int $beforets): int {
        global $DB;
        // Never purge rows of the student's current year/semester.
        $sql = "lastsynced < :t AND NOT EXISTS (
            SELECT 1 FROM {local_kaznu_univer_user} u
             WHERE u.userid = {local_kaznu_schedule}.userid
               AND u.current_year = {local_kaznu_schedule}.academyear
               AND u.current_semester = {local_kaznu_schedule}.academsemester
        )";
        return (int) $DB->delete_records_select('local_kaznu_schedule', $sql, ['t' => $beforets]);
    }

    private function map_item(int $userid, string $type, int $year, int $semester, object $item, int $now): ?\stdClass {
        if ($item instanceof schedule_item) {
            return (object) [
                'userid' => $userid,
                'itemtype' => cache_module::SCHEDULE_TYPE_LESSON,
                'academyear' => $year,
                'academsemester' => $semester,
                'external_key' => $item->external_key(),
                'subjectname' => $item->subject,
                'lesson_type' => $item->eductype,
                'teacher' => $item->teacher,
                'building' => $item->audiencefaculty,
                'audience' => $item->audience,
                'weekday' => $item->weekday,
                'timebegin' => $item->timebegin,
                'timeend' => $item->timeend,
                'datefrom' => $item->datefrom,
                'dateto' => $item->dateto,
                'eventdate' => 0,
                'eventtime' => '',
                'denominator' => $item->denominator,
                'apitype' => (string) $item->type,
                'lastsynced' => $now,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
        }
        if ($item instanceof exam_item || $item instanceof midterm_item) {
            $date = univer_datetime::parse_date($item->date);
            return (object) [
                'userid' => $userid,
                'itemtype' => $type,
                'academyear' => $year,
                'academsemester' => $semester,
                'external_key' => $item->external_key(),
                'subjectname' => $item->subject,
                'lesson_type' => $item->examtype,
                'teacher' => $item->teacher,
                'building' => $item->audiencefaculty,
                'audience' => $item->audience,
                'weekday' => 0,
                'timebegin' => $item->time,
                'timeend' => '',
                'datefrom' => '',
                'dateto' => '',
                'eventdate' => $date,
                'eventtime' => $item->time,
                'denominator' => 0,
                'apitype' => $item->examtype,
                'lastsynced' => $now,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
        }
        return null;
    }
}
