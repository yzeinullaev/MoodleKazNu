<?php
namespace local_kaznu\service;

use local_kaznu\api\dto\semester_info;
use local_kaznu\api\univer_client_interface;
use local_kaznu\repository\sync_log_repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Full student snapshot sync from Univer while an authenticated client session is open.
 * Syncs current academic period and the previous one (fallback for empty new semester).
 */
class full_student_sync_service {
    public function __construct(
        private readonly ?subject_service $subjects = null,
        private readonly ?schedule_service $schedule = null,
        private readonly ?assessment_service $assessments = null,
        private readonly ?attendance_service $attendance = null,
        private readonly ?sync_log_repository $logs = null
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

    private function logs(): sync_log_repository {
        return $this->logs ?? new sync_log_repository();
    }

    /**
     * @return array<string,mixed>
     */
    public function sync_all(int $userid, univer_client_interface $client, ?semester_info $semester = null): array {
        $out = [
            'ok' => true,
            'periods' => [],
            'primary' => null,
        ];

        if (!$semester) {
            try {
                $semester = $client->get_semester_info();
            } catch (\Throwable $e) {
                $out['ok'] = false;
                $out['error'] = 'no_semester';
                $this->logs()->add($userid, 'full_sync', 'error', 0, 0, 'no_semester');
                return $out;
            }
        }

        $periods = academic_period_resolver::periods_to_sync($semester);
        $out['primary'] = [
            'year' => $semester->currentyear,
            'semester' => $semester->currentsemester,
        ];

        foreach ($periods as $period) {
            $info = new semester_info(
                $period['year'],
                $period['semester'],
                $semester->semesterscount,
                $semester->currentrk
            );
            $periodresult = $this->sync_period($userid, $client, $info);
            $out['periods'][] = $periodresult;
            if (empty($periodresult['ok'])) {
                $out['ok'] = false;
            }
        }

        $detail = [];
        foreach ($out['periods'] as $p) {
            $detail[] = $p['year'] . '/' . $p['semester']
                . ':subj=' . ($p['subjects']['upserted'] ?? 0)
                . ',L=' . ($p['schedule']['lessons'] ?? 0)
                . ',A=' . ($p['assessment']['count'] ?? 0);
        }
        $this->logs()->add($userid, 'full_sync', $out['ok'] ? 'ok' : 'partial', 0, 0, implode('; ', $detail));
        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    private function sync_period(int $userid, univer_client_interface $client, semester_info $semester): array {
        $result = [
            'year' => $semester->currentyear,
            'semester' => $semester->currentsemester,
            'ok' => true,
            'subjects' => ['upserted' => 0],
            'schedule' => ['lessons' => 0, 'exams' => 0, 'midterms' => 0],
            'assessment' => ['count' => 0],
            'attendance' => ['count' => 0],
        ];

        try {
            $result['subjects'] = $this->subjects()->sync_from_client($userid, $client, $semester);
        } catch (\Throwable $e) {
            $result['ok'] = false;
            $result['subjects_error'] = get_class($e);
        }

        try {
            $result['schedule'] = $this->schedule()->sync_from_client($userid, $client, $semester);
        } catch (\Throwable $e) {
            $result['ok'] = false;
            $result['schedule_error'] = get_class($e);
        }

        try {
            $result['assessment'] = $this->assessments()->sync_from_client($userid, $client, $semester);
        } catch (\Throwable $e) {
            $result['ok'] = false;
            $result['assessment_error'] = get_class($e);
        }

        try {
            $result['attendance'] = $this->attendance()->sync_from_client($userid, $client, $semester);
        } catch (\Throwable $e) {
            $result['ok'] = false;
            $result['attendance_error'] = get_class($e);
        }

        return $result;
    }
}
