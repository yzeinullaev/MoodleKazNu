<?php
namespace local_kaznu\repository;

use local_kaznu\cache_module;

defined('MOODLE_INTERNAL') || die();

/**
 * Per-module last success / error (no PII).
 */
class cache_state_repository {
    /**
     * @return \stdClass|false
     */
    public function get(int $userid, string $module, int $year, int $semester) {
        global $DB;
        return $DB->get_record('local_kaznu_cache_state', [
            'userid' => $userid,
            'module' => $module,
            'academyear' => $year,
            'academsemester' => $semester,
        ]);
    }

    public function mark_success(int $userid, string $module, int $year, int $semester, int $rowcount, int $durationms = 0): void {
        $this->upsert($userid, $module, $year, $semester, [
            'lastsuccess' => time(),
            'lastattempt' => time(),
            'lasterror' => 0,
            'last_error_code' => '',
            'rowcount' => $rowcount,
            'durationms' => $durationms,
        ]);
    }

    public function mark_failure(int $userid, string $module, int $year, int $semester, string $errorcode, int $durationms = 0): void {
        $this->upsert($userid, $module, $year, $semester, [
            'lastattempt' => time(),
            'lasterror' => time(),
            'last_error_code' => substr($errorcode, 0, 40),
            'durationms' => $durationms,
        ]);
    }

    /**
     * @param array<string,int|string> $fields
     */
    private function upsert(int $userid, string $module, int $year, int $semester, array $fields): void {
        global $DB;
        $now = time();
        $existing = $this->get($userid, $module, $year, $semester);
        if ($existing) {
            foreach ($fields as $k => $v) {
                $existing->$k = $v;
            }
            $existing->timemodified = $now;
            $DB->update_record('local_kaznu_cache_state', $existing);
            return;
        }
        $rec = (object) array_merge([
            'userid' => $userid,
            'module' => $module,
            'academyear' => $year,
            'academsemester' => $semester,
            'lastsuccess' => 0,
            'lastattempt' => 0,
            'lasterror' => 0,
            'last_error_code' => '',
            'rowcount' => 0,
            'durationms' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ], $fields);
        $DB->insert_record('local_kaznu_cache_state', $rec);
    }

    public function freshness(int $userid, string $module, int $year, int $semester, int $ttlseconds): string {
        $state = $this->get($userid, $module, $year, $semester);
        if (!$state || (int) $state->lastsuccess <= 0) {
            // Failed attempt with no successful snapshot yet.
            if ($state && (int) $state->lasterror > 0) {
                return 'unavailable';
            }
            return 'empty';
        }
        // Failure on/after last success (same-second clocks use >=): unavailable banner; TTL does not hide errors.
        if ((int) $state->lasterror > 0 && (int) $state->lasterror >= (int) $state->lastsuccess) {
            return 'unavailable';
        }
        $age = time() - (int) $state->lastsuccess;
        if ($age <= $ttlseconds) {
            return 'fresh';
        }
        return 'stale';
    }

    public function delete_for_userid(int $userid): void {
        global $DB;
        $DB->delete_records('local_kaznu_cache_state', ['userid' => $userid]);
    }

    /**
     * Retention cleanup for orphaned/very old state rows.
     */
    public function purge_older_than(int $beforets): int {
        global $DB;
        // State for the current period is kept so status banners remain accurate.
        $sql = "lastsuccess > 0 AND lastsuccess < :t AND lastattempt < :t2 AND NOT EXISTS (
            SELECT 1 FROM {local_kaznu_univer_user} u
             WHERE u.userid = {local_kaznu_cache_state}.userid
               AND u.current_year = {local_kaznu_cache_state}.academyear
               AND u.current_semester = {local_kaznu_cache_state}.academsemester
        )";
        return (int) $DB->delete_records_select(
            'local_kaznu_cache_state',
            $sql,
            ['t' => $beforets, 't2' => $beforets]
        );
    }
}
