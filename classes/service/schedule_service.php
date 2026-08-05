<?php
namespace local_kaznu\service;

use local_kaznu\api\dto\semester_info;
use local_kaznu\api\univer_client_interface;
use local_kaznu\api\univer_exception;
use local_kaznu\cache_module;
use local_kaznu\event\student_data_sync_failed;
use local_kaznu\event\student_schedule_synced;
use local_kaznu\repository\cache_state_repository;
use local_kaznu\repository\schedule_repository;
use local_kaznu\repository\sync_log_repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Lesson / exam / midterm cache (Univer read-only).
 */
class schedule_service {
    public function __construct(
        private readonly ?schedule_repository $repo = null,
        private readonly ?cache_state_repository $state = null,
        private readonly ?sync_log_repository $logs = null
    ) {
    }

    private function repo(): schedule_repository {
        return $this->repo ?? new schedule_repository();
    }

    private function state(): cache_state_repository {
        return $this->state ?? new cache_state_repository();
    }

    private function logs(): sync_log_repository {
        return $this->logs ?? new sync_log_repository();
    }

    /**
     * Live pull while session valid. Failures do not throw by default.
     *
     * @return array{ok:bool,lessons:int,exams:int,midterms:int,error?:string}
     */
    public function sync_from_client(int $userid, univer_client_interface $client, semester_info $semester, string $culture = ''): array {
        $culture = $culture !== '' ? $culture : (string) (get_config('local_kaznu', 'univer_culture') ?: 'ru-RU');
        $year = $semester->currentyear;
        $sem = $semester->currentsemester;
        $result = ['ok' => true, 'lessons' => 0, 'exams' => 0, 'midterms' => 0];

        $result['lessons'] = $this->sync_module(
            $userid,
            cache_module::SCHEDULE,
            cache_module::SCHEDULE_TYPE_LESSON,
            $year,
            $sem,
            function () use ($client, $year, $sem, $culture) {
                return $client->get_schedule_week($year, $sem, $culture);
            }
        ) ?? 0;

        $ex = $this->sync_module(
            $userid,
            cache_module::EXAM,
            cache_module::SCHEDULE_TYPE_EXAM,
            $year,
            $sem,
            function () use ($client, $year, $sem, $culture) {
                return $client->get_exam_schedule($year, $sem, $culture);
            }
        );
        $result['exams'] = $ex ?? 0;

        $mid = $this->sync_module(
            $userid,
            cache_module::MIDTERM,
            cache_module::SCHEDULE_TYPE_MIDTERM,
            $year,
            $sem,
            function () use ($client, $year, $sem, $culture) {
                return $client->get_midterm_schedule($year, $sem, $culture);
            }
        );
        $result['midterms'] = $mid ?? 0;

        student_schedule_synced::create_safe($userid, $result['lessons'], $result['exams'], $result['midterms'])->trigger();
        $this->logs()->add($userid, 'schedule_sync', 'ok', 0, 0, 'L=' . $result['lessons'] . ',E=' . $result['exams'] . ',M=' . $result['midterms']);
        return $result;
    }

    /**
     * Replace from DTO list (tests).
     *
     * @param object[] $items
     */
    public function sync_dtos(int $userid, string $type, int $year, int $semester, array $items): int {
        $module = match ($type) {
            cache_module::SCHEDULE_TYPE_EXAM => cache_module::EXAM,
            cache_module::SCHEDULE_TYPE_MIDTERM => cache_module::MIDTERM,
            default => cache_module::SCHEDULE,
        };
        $t0 = microtime(true);
        $n = $this->repo()->replace_snapshot($userid, $type, $year, $semester, $items);
        $ms = (int) ((microtime(true) - $t0) * 1000);
        $this->state()->mark_success($userid, $module, $year, $semester, $n, $ms);
        return $n;
    }

    /**
     * @param callable():array $fetcher
     * @return int|null null = failed (old cache kept)
     */
    private function sync_module(int $userid, string $module, string $type, int $year, int $semester, callable $fetcher): ?int {
        $t0 = microtime(true);
        try {
            $items = $fetcher();
            $n = $this->repo()->replace_snapshot($userid, $type, $year, $semester, $items);
            $ms = (int) ((microtime(true) - $t0) * 1000);
            $this->state()->mark_success($userid, $module, $year, $semester, $n, $ms);
            return $n;
        } catch (univer_exception $e) {
            $ms = (int) ((microtime(true) - $t0) * 1000);
            $this->state()->mark_failure($userid, $module, $year, $semester, $e->errorcode, $ms);
            $this->logs()->add($userid, $module . '_sync', 'error', 0, 0, $e->errorcode);
            student_data_sync_failed::create_safe($userid, $module, $e->errorcode)->trigger();
            return null;
        } catch (\Throwable $e) {
            $ms = (int) ((microtime(true) - $t0) * 1000);
            $this->state()->mark_failure($userid, $module, $year, $semester, 'univer_sync_error', $ms);
            $this->logs()->add($userid, $module . '_sync', 'error', 0, 0, 'univer_sync_error');
            student_data_sync_failed::create_safe($userid, $module, 'univer_sync_error')->trigger();
            return null;
        }
    }

    /**
     * @return \stdClass[]
     */
    public function get_lessons(int $userid, int $year, int $semester): array {
        return $this->repo()->get_for_user($userid, cache_module::SCHEDULE_TYPE_LESSON, $year, $semester);
    }

    /**
     * @return \stdClass[]
     */
    public function get_exams(int $userid, int $year, int $semester): array {
        return $this->repo()->get_for_user($userid, cache_module::SCHEDULE_TYPE_EXAM, $year, $semester);
    }

    /**
     * @return \stdClass[]
     */
    public function get_midterms(int $userid, int $year, int $semester): array {
        return $this->repo()->get_for_user($userid, cache_module::SCHEDULE_TYPE_MIDTERM, $year, $semester);
    }
}
