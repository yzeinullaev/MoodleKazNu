<?php
namespace local_kaznu\service;

use local_kaznu\api\dto\assessment_item;
use local_kaznu\api\dto\semester_info;
use local_kaznu\api\univer_client_interface;
use local_kaznu\api\univer_exception;
use local_kaznu\cache_module;
use local_kaznu\event\student_assessments_synced;
use local_kaznu\event\student_data_sync_failed;
use local_kaznu\repository\assessment_repository;
use local_kaznu\repository\cache_state_repository;
use local_kaznu\repository\sync_log_repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Attestation / GPA cache. Never writes moodle grade_grades.
 */
class assessment_service {
    public function __construct(
        private readonly ?assessment_repository $repo = null,
        private readonly ?cache_state_repository $state = null,
        private readonly ?sync_log_repository $logs = null
    ) {
    }

    private function repo(): assessment_repository {
        return $this->repo ?? new assessment_repository();
    }

    private function state(): cache_state_repository {
        return $this->state ?? new cache_state_repository();
    }

    private function logs(): sync_log_repository {
        return $this->logs ?? new sync_log_repository();
    }

    /**
     * @return array{ok:bool,count:int}
     */
    public function sync_from_client(int $userid, univer_client_interface $client, semester_info $semester, string $culture = ''): array {
        $culture = $culture !== '' ? $culture : (string) (get_config('local_kaznu', 'univer_culture') ?: 'ru-RU');
        $year = $semester->currentyear;
        $sem = $semester->currentsemester;
        $t0 = microtime(true);
        try {
            $items = $client->get_attestation($year, $sem, $culture);
            $n = $this->repo()->replace_snapshot($userid, $year, $sem, $items);
            $ms = (int) ((microtime(true) - $t0) * 1000);
            $this->state()->mark_success($userid, cache_module::ASSESSMENT, $year, $sem, $n, $ms);
            $this->logs()->add($userid, 'assessment_sync', 'ok', 0, 0, 'n=' . $n);
            student_assessments_synced::create_safe($userid, $n)->trigger();
            return ['ok' => true, 'count' => $n];
        } catch (univer_exception $e) {
            $ms = (int) ((microtime(true) - $t0) * 1000);
            $this->state()->mark_failure($userid, cache_module::ASSESSMENT, $year, $sem, $e->errorcode, $ms);
            $this->logs()->add($userid, 'assessment_sync', 'error', 0, 0, $e->errorcode);
            student_data_sync_failed::create_safe($userid, cache_module::ASSESSMENT, $e->errorcode)->trigger();
            return ['ok' => false, 'count' => 0];
        } catch (\Throwable $e) {
            $ms = (int) ((microtime(true) - $t0) * 1000);
            $this->state()->mark_failure($userid, cache_module::ASSESSMENT, $year, $sem, 'univer_sync_error', $ms);
            $this->logs()->add($userid, 'assessment_sync', 'error', 0, 0, 'univer_sync_error');
            student_data_sync_failed::create_safe($userid, cache_module::ASSESSMENT, 'univer_sync_error')->trigger();
            return ['ok' => false, 'count' => 0];
        }
    }

    /**
     * @param assessment_item[] $items
     */
    public function sync_dtos(int $userid, int $year, int $semester, array $items): int {
        $n = $this->repo()->replace_snapshot($userid, $year, $semester, $items);
        $this->state()->mark_success($userid, cache_module::ASSESSMENT, $year, $semester, $n, 0);
        return $n;
    }

    /**
     * @return \stdClass[]
     */
    public function get_for_user(int $userid, int $year = 0, int $semester = 0): array {
        return $this->repo()->get_for_user($userid, $year, $semester);
    }
}
