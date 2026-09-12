<?php
namespace local_kaznu\service;

use local_kaznu\api\dto\semester_info;
use local_kaznu\api\univer_client_interface;
use local_kaznu\cache_module;
use local_kaznu\repository\cache_state_repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Assembles My Univer dashboard from local cache only (no live HTTP).
 * Coordinates Phase 5 live sync at login/refresh without blocking login.
 */
class student_dashboard_service {
    public function __construct(
        private readonly ?subject_service $subjects = null,
        private readonly ?schedule_service $schedule = null,
        private readonly ?assessment_service $assessments = null,
        private readonly ?attendance_service $attendance = null,
        private readonly ?cache_state_repository $state = null
    ) {
    }

    private function subjects(): subject_service {
        return $this->subjects ?? new subject_service();
    }

    private function schedule(): schedule_service {
        return $this->schedule ?? new schedule_service();
    }

    private function assessments(): assessment_service {
        return $this->assessments ?? new assessment_service();
    }

    private function attendance(): attendance_service {
        return $this->attendance ?? new attendance_service();
    }

    private function state(): cache_state_repository {
        return $this->state ?? new cache_state_repository();
    }

    /**
     * Live Phase 5 modules (each isolated). Never throws.
     *
     * @return array<string,mixed>
     */
    public function sync_live_modules(int $userid, univer_client_interface $client, semester_info $semester): array {
        $out = [];
        try {
            $out['schedule'] = $this->schedule()->sync_from_client($userid, $client, $semester);
        } catch (\Throwable $e) {
            $out['schedule'] = ['ok' => false];
        }
        try {
            $out['assessment'] = $this->assessments()->sync_from_client($userid, $client, $semester);
        } catch (\Throwable $e) {
            $out['assessment'] = ['ok' => false];
        }
        try {
            $out['attendance'] = $this->attendance()->sync_from_client($userid, $client, $semester);
        } catch (\Throwable $e) {
            $out['attendance'] = ['ok' => false];
        }
        return $out;
    }

    /**
     * UI payload from DB for one Moodle user only.
     *
     * @param string $section overview|courses|schedule|grades|assessments|attendance
     * @return array<string,mixed>
     */
    public function get_cabinet(int $userid, string $section = 'overview'): array {
        // grades ≡ assessments (RC product alias).
        if ($section === 'grades') {
            $section = 'assessments';
        }
        if ($section === 'courses') {
            // Courses list is assembled in my.php; no extra cache load.
            $section = 'overview';
        }

        $base = $this->subjects()->get_cabinet_data($userid);
        $profile = $base['profile'];
        $profileyear = $profile ? (int) $profile->current_year : 0;
        $profilesem = $profile ? (int) $profile->current_semester : 0;
        $resolved = academic_period_resolver::resolve_for_user($userid, $profileyear, $profilesem);
        $year = (int) $resolved['year'];
        $sem = (int) $resolved['semester'];

        $status = $this->build_status($userid, $year, $sem);

        $out = [
            'profile' => $profile,
            'subjects' => $base['subjects'],
            'year' => $year,
            'semester' => $sem,
            'period_source' => $resolved['source'],
            'profile_year' => $profileyear,
            'profile_semester' => $profilesem,
            'lessons' => [],
            'exams' => [],
            'midterms' => [],
            'assessments' => [],
            'attendance' => [],
            'status' => $status,
        ];

        if ($year <= 0) {
            return $out;
        }

        // Lazy load by tab to avoid extra queries on overview.
        if ($section === 'schedule') {
            $out['lessons'] = $this->schedule()->get_lessons($userid, $year, $sem);
            $out['exams'] = $this->schedule()->get_exams($userid, $year, $sem);
            $out['midterms'] = $this->schedule()->get_midterms($userid, $year, $sem);
        } else if ($section === 'assessments') {
            $out['assessments'] = $this->assessments()->get_for_user($userid, $year, $sem);
        } else if ($section === 'attendance') {
            $out['attendance'] = $this->attendance()->get_for_user($userid, $year, $sem);
        }

        return $out;
    }

    /**
     * @return array<string,array{freshness:string,lastsuccess:int,lasterror:int,errorcode:string,rowcount:int}>
     */
    private function build_status(int $userid, int $year, int $sem): array {
        global $DB;

        $ttlmap = [
            cache_module::SCHEDULE => (int) (get_config('local_kaznu', 'univer_schedule_ttl') ?: 900),
            cache_module::EXAM => (int) (get_config('local_kaznu', 'univer_assessment_ttl') ?: 1800),
            cache_module::MIDTERM => (int) (get_config('local_kaznu', 'univer_assessment_ttl') ?: 1800),
            cache_module::ASSESSMENT => (int) (get_config('local_kaznu', 'univer_assessment_ttl') ?: 1800),
            cache_module::ATTENDANCE => (int) (get_config('local_kaznu', 'univer_attendance_ttl') ?: 1800),
        ];

        $rows = [];
        if ($year > 0) {
            // Single query for all modules (no N×state lookups).
            $raw = $DB->get_records('local_kaznu_cache_state', [
                'userid' => $userid,
                'academyear' => $year,
                'academsemester' => $sem,
            ]);
            foreach ($raw as $r) {
                $rows[(string) $r->module] = $r;
            }
        }

        $status = [];
        foreach ($ttlmap as $mod => $ttl) {
            $st = $rows[$mod] ?? null;
            $status[$mod] = [
                'freshness' => $this->freshness_from_row($st, max(60, $ttl)),
                'lastsuccess' => $st ? (int) $st->lastsuccess : 0,
                'lasterror' => $st ? (int) $st->lasterror : 0,
                'errorcode' => $st ? (string) $st->last_error_code : '',
                'rowcount' => $st ? (int) $st->rowcount : 0,
            ];
        }
        return $status;
    }

    private function freshness_from_row(?\stdClass $state, int $ttlseconds): string {
        if (!$state || (int) $state->lastsuccess <= 0) {
            if ($state && (int) $state->lasterror > 0) {
                return 'unavailable';
            }
            return 'empty';
        }
        if ((int) $state->lasterror > 0 && (int) $state->lasterror >= (int) $state->lastsuccess) {
            return 'unavailable';
        }
        $age = time() - (int) $state->lastsuccess;
        if ($age <= $ttlseconds) {
            return 'fresh';
        }
        return 'stale';
    }
}
