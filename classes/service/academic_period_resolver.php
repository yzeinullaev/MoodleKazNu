<?php
namespace local_kaznu\service;

use local_kaznu\api\dto\semester_info;
use local_kaznu\cache_module;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolve which academic year/semester to show when Univer current period is empty.
 */
class academic_period_resolver {
    /**
     * Periods to pull at login: current, then previous.
     *
     * @return array<int,array{year:int,semester:int}>
     */
    public static function periods_to_sync(semester_info $semester): array {
        $cur = [
            'year' => (int) $semester->currentyear,
            'semester' => (int) $semester->currentsemester,
        ];
        $prev = self::previous_period($cur['year'], $cur['semester']);
        $out = [];
        if ($cur['year'] > 0 && $cur['semester'] > 0) {
            $out[] = $cur;
        }
        if ($prev && ($prev['year'] !== $cur['year'] || $prev['semester'] !== $cur['semester'])) {
            $out[] = $prev;
        }
        return $out;
    }

    /**
     * @return array{year:int,semester:int}|null
     */
    public static function previous_period(int $year, int $semester): ?array {
        if ($year <= 0 || $semester <= 0) {
            return null;
        }
        if ($semester >= 2) {
            return ['year' => $year, 'semester' => $semester - 1];
        }
        return ['year' => $year - 1, 'semester' => 2];
    }

    /**
     * Prefer profile period; if empty for schedule/subjects/grades, fall back to latest with rows.
     *
     * @return array{year:int,semester:int,source:string}
     */
    public static function resolve_for_user(int $userid, int $profileyear, int $profilesem): array {
        global $DB;

        $profileyear = max(0, $profileyear);
        $profilesem = max(0, $profilesem);

        if ($profileyear > 0 && self::period_has_data($userid, $profileyear, $profilesem)) {
            return ['year' => $profileyear, 'semester' => $profilesem, 'source' => 'profile'];
        }

        // Prefer previous period relative to profile.
        $prev = self::previous_period($profileyear, $profilesem);
        if ($prev && self::period_has_data($userid, $prev['year'], $prev['semester'])) {
            return ['year' => $prev['year'], 'semester' => $prev['semester'], 'source' => 'previous'];
        }

        // Latest period that has any cached student rows.
        $candidates = [];
        foreach ([
            "SELECT academyear AS y, academsemester AS s, MAX(lastsynced) AS ts FROM {local_kaznu_schedule} WHERE userid = ? GROUP BY academyear, academsemester",
            "SELECT academyear AS y, academsemester AS s, MAX(lastsynced) AS ts FROM {local_kaznu_assessment} WHERE userid = ? GROUP BY academyear, academsemester",
            "SELECT academyear AS y, academsemester AS s, MAX(lastsynced) AS ts FROM {local_kaznu_attendance} WHERE userid = ? GROUP BY academyear, academsemester",
            "SELECT academyear AS y, academsemester AS s, MAX(lastsynced) AS ts FROM {local_kaznu_univer_subject} WHERE userid = ? GROUP BY academyear, academsemester",
        ] as $sql) {
            try {
                $rows = $DB->get_records_sql($sql, [$userid]);
            } catch (\Throwable $e) {
                $rows = [];
            }
            foreach ($rows as $r) {
                $key = ((int) $r->y) . ':' . ((int) $r->s);
                $ts = (int) ($r->ts ?? 0);
                if (!isset($candidates[$key]) || $ts > $candidates[$key]['ts']) {
                    $candidates[$key] = ['year' => (int) $r->y, 'semester' => (int) $r->s, 'ts' => $ts];
                }
            }
        }

        if ($candidates) {
            usort($candidates, static fn($a, $b) => $b['ts'] <=> $a['ts']);
            $best = reset($candidates);
            return ['year' => $best['year'], 'semester' => $best['semester'], 'source' => 'latest_data'];
        }

        // Cache state success with rows.
        $states = $DB->get_records_select(
            'local_kaznu_cache_state',
            'userid = ? AND rowcount > 0',
            [$userid],
            'lastsuccess DESC',
            '*',
            0,
            1
        );
        if ($states) {
            $st = reset($states);
            return [
                'year' => (int) $st->academyear,
                'semester' => (int) $st->academsemester,
                'source' => 'cache_state',
            ];
        }

        return ['year' => $profileyear, 'semester' => $profilesem, 'source' => 'profile_empty'];
    }

    public static function period_has_data(int $userid, int $year, int $sem): bool {
        global $DB;
        if ($year <= 0 || $sem <= 0) {
            return false;
        }
        if ($DB->record_exists('local_kaznu_univer_subject', [
            'userid' => $userid, 'academyear' => $year, 'academsemester' => $sem,
        ])) {
            return true;
        }
        if ($DB->record_exists('local_kaznu_schedule', [
            'userid' => $userid, 'academyear' => $year, 'academsemester' => $sem,
        ])) {
            return true;
        }
        if ($DB->record_exists('local_kaznu_assessment', [
            'userid' => $userid, 'academyear' => $year, 'academsemester' => $sem,
        ])) {
            return true;
        }
        if ($DB->record_exists('local_kaznu_attendance', [
            'userid' => $userid, 'academyear' => $year, 'academsemester' => $sem,
        ])) {
            return true;
        }
        // Successful cache_state with rows.
        return $DB->record_exists_select(
            'local_kaznu_cache_state',
            'userid = ? AND academyear = ? AND academsemester = ? AND rowcount > 0',
            [$userid, $year, $sem]
        );
    }
}
