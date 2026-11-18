<?php
namespace local_kaznu\repository;

use local_kaznu\api\dto\subject;
use local_kaznu\univer_status;

defined('MOODLE_INTERNAL') || die();

/**
 * Persistence for local_kaznu_univer_subject cache rows.
 */
class univer_subject_repository {
    /**
     * @return \stdClass|false
     */
    public function get_by_id(int $id) {
        global $DB;
        return $DB->get_record('local_kaznu_univer_subject', ['id' => $id]);
    }

    /**
     * @return \stdClass|false
     */
    public function get_unique(int $userid, int $subjectid, int $year, int $semester) {
        global $DB;
        return $DB->get_record('local_kaznu_univer_subject', [
            'userid' => $userid,
            'subjectid' => $subjectid,
            'academyear' => $year,
            'academsemester' => $semester,
        ]);
    }

    /**
     * @return \stdClass[]
     */
    public function get_for_user(int $userid, ?string $status = null): array {
        global $DB;
        $params = ['userid' => $userid];
        $sql = "SELECT * FROM {local_kaznu_univer_subject} WHERE userid = :userid";
        if ($status !== null) {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }
        $sql .= " ORDER BY academyear DESC, academsemester DESC, subjectname ASC";
        return array_values($DB->get_records_sql($sql, $params));
    }

    /**
     * All subject rows (for reconcile task).
     *
     * @return \stdClass[]
     */
    public function get_all(?string $status = null, int $limit = 0): array {
        global $DB;
        $params = [];
        $sql = "SELECT * FROM {local_kaznu_univer_subject}";
        if ($status !== null) {
            $sql .= " WHERE status = :status";
            $params['status'] = $status;
        }
        $sql .= " ORDER BY id ASC";
        if ($limit > 0) {
            return array_values($DB->get_records_sql($sql, $params, 0, $limit));
        }
        return array_values($DB->get_records_sql($sql, $params));
    }

    public function update_enrol_state(int $rowid, int $moodlecourseid, int $enrolled): void {
        global $DB;
        $DB->update_record('local_kaznu_univer_subject', (object) [
            'id' => $rowid,
            'moodlecourseid' => $moodlecourseid,
            'enrolled' => $enrolled ? 1 : 0,
            'timemodified' => time(),
        ]);
    }

    /**
     * Upsert live subjects for period; mark missing ones stale (never delete).
     *
     * @param subject[] $subjects
     * @return array{upserted:int,reactivated:int,staled:int,staled_ids:int[]}
     */
    public function sync_period(int $userid, int $year, int $semester, array $subjects): array {
        global $DB;

        $now = time();
        $seenkeys = [];
        $upserted = 0;
        $reactivated = 0;

        foreach ($subjects as $dto) {
            if (!$dto instanceof subject) {
                continue;
            }
            $key = $dto->subjectid . ':' . $dto->academyear . ':' . $dto->academsemester;
            if (isset($seenkeys[$key])) {
                continue;
            }
            $seenkeys[$key] = true;

            $existing = $this->get_unique($userid, $dto->subjectid, $dto->academyear, $dto->academsemester);
            if ($existing) {
                $wasstale = ((string) $existing->status === univer_status::STALE);
                $existing->subjectname = $dto->subjectname;
                $existing->course_idnumber = $dto->course_idnumber();
                $existing->groupid = $dto->groupid;
                $existing->planposid = $dto->planposid;
                $existing->eductypeid = $dto->eductypeid;
                $existing->eductypename = $dto->eductypename;
                $existing->status = univer_status::ACTIVE;
                $existing->lastseen = $now;
                $existing->timemodified = $now;
                $DB->update_record('local_kaznu_univer_subject', $existing);
                $upserted++;
                if ($wasstale) {
                    $reactivated++;
                }
            } else {
                $rec = (object) [
                    'userid' => $userid,
                    'subjectid' => $dto->subjectid,
                    'academyear' => $dto->academyear,
                    'academsemester' => $dto->academsemester,
                    'course_idnumber' => $dto->course_idnumber(),
                    'subjectname' => $dto->subjectname,
                    'groupid' => $dto->groupid,
                    'planposid' => $dto->planposid,
                    'eductypeid' => $dto->eductypeid,
                    'eductypename' => $dto->eductypename,
                    'status' => univer_status::ACTIVE,
                    'moodlecourseid' => 0,
                    'enrolled' => 0,
                    'firstseen' => $now,
                    'lastseen' => $now,
                    'timemodified' => $now,
                ];
                $DB->insert_record('local_kaznu_univer_subject', $rec);
                $upserted++;
            }
        }

        $staled = 0;
        $staledids = [];
        $existingperiod = $DB->get_records('local_kaznu_univer_subject', [
            'userid' => $userid,
            'academyear' => $year,
            'academsemester' => $semester,
        ]);
        foreach ($existingperiod as $row) {
            $key = (int) $row->subjectid . ':' . (int) $row->academyear . ':' . (int) $row->academsemester;
            if (isset($seenkeys[$key])) {
                continue;
            }
            if ((string) $row->status === univer_status::STALE) {
                continue;
            }
            $row->status = univer_status::STALE;
            $row->timemodified = $now;
            $DB->update_record('local_kaznu_univer_subject', $row);
            $staled++;
            $staledids[] = (int) $row->id;
        }

        return [
            'upserted' => $upserted,
            'reactivated' => $reactivated,
            'staled' => $staled,
            'staled_ids' => $staledids,
        ];
    }

    public function delete_for_userid(int $userid): void {
        global $DB;
        $DB->delete_records('local_kaznu_univer_subject', ['userid' => $userid]);
    }
}
