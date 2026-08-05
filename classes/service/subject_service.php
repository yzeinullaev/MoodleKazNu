<?php
namespace local_kaznu\service;

use local_kaznu\api\dto\semester_info;
use local_kaznu\api\dto\subject;
use local_kaznu\api\univer_client_interface;
use local_kaznu\event\student_subject_marked_stale;
use local_kaznu\event\student_subjects_synced;
use local_kaznu\repository\sync_log_repository;
use local_kaznu\repository\univer_subject_repository;
use local_kaznu\univer_status;

defined('MOODLE_INTERNAL') || die();

/**
 * Univer subject cache + enrol orchestration (not user provisioning).
 */
class subject_service {
    public function __construct(
        private readonly ?univer_subject_repository $subjects = null,
        private readonly ?enrolment_service $enrolment = null,
        private readonly ?sync_log_repository $logs = null
    ) {
    }

    private function subjects(): univer_subject_repository {
        return $this->subjects ?? new univer_subject_repository();
    }

    private function enrolment(): enrolment_service {
        return $this->enrolment ?? new enrolment_service($this->subjects());
    }

    private function logs(): sync_log_repository {
        return $this->logs ?? new sync_log_repository();
    }

    /**
     * Pull groupList from an authenticated client and upsert + enrol.
     *
     * @return array{upserted:int,reactivated:int,staled:int,enrolled:int,not_found:int,errors:int}
     */
    public function sync_from_client(
        int $userid,
        univer_client_interface $client,
        semester_info $semester,
        string $culture = ''
    ): array {
        $culture = $culture !== '' ? $culture : (string) (get_config('local_kaznu', 'univer_culture') ?: 'ru-RU');
        $list = $client->get_group_list($semester->currentyear, $semester->currentsemester, $culture);
        return $this->sync_dtos($userid, $semester->currentyear, $semester->currentsemester, $list);
    }

    /**
     * Upsert DTO list (tests + live). Marks missing stale; enrols active.
     *
     * @param subject[] $subjects
     * @return array{upserted:int,reactivated:int,staled:int,enrolled:int,not_found:int,errors:int}
     */
    public function sync_dtos(int $userid, int $year, int $semester, array $subjects): array {
        $stats = $this->subjects()->sync_period($userid, $year, $semester, $subjects);

        foreach ($stats['staled_ids'] as $rowid) {
            $row = $this->subjects()->get_by_id($rowid);
            if (!$row) {
                continue;
            }
            // Policy: no unenrol; enrolled=0 only when Moodle enrolment missing.
            $this->enrolment()->reconcile_row($row);
            student_subject_marked_stale::create_safe($userid, (int) $row->subjectid, (int) $row->academyear, (int) $row->academsemester)
                ->trigger();
            $this->logs()->add($userid, 'subject', 'stale', (int) $row->subjectid, (int) $row->moodlecourseid);
        }

        $enrolled = 0;
        $notfound = 0;
        $errors = 0;
        $active = $this->subjects()->get_for_user($userid, univer_status::ACTIVE);
        foreach ($active as $row) {
            if ((int) $row->academyear !== $year || (int) $row->academsemester !== $semester) {
                // Only enrol current period rows from this sync.
                continue;
            }
            $result = $this->enrolment()->ensure_for_subject_row($row);
            if ($result['status'] === 'enrolled' || $result['status'] === 'already') {
                $enrolled++;
            } else if ($result['status'] === 'not_found') {
                $notfound++;
            } else if ($result['status'] === 'error') {
                $errors++;
            }
        }

        $this->logs()->add(
            $userid,
            'subjects_sync',
            'ok',
            0,
            0,
            'u=' . $stats['upserted'] . ',r=' . $stats['reactivated'] . ',s=' . $stats['staled']
        );
        student_subjects_synced::create_safe(
            $userid,
            $stats['upserted'],
            $stats['staled'],
            $stats['reactivated'],
            $enrolled
        )->trigger();

        return [
            'upserted' => $stats['upserted'],
            'reactivated' => $stats['reactivated'],
            'staled' => $stats['staled'],
            'enrolled' => $enrolled,
            'not_found' => $notfound,
            'errors' => $errors,
        ];
    }

    /**
     * Cache-only view for My Univer (no live HTTP).
     *
     * @return array{profile:?\stdClass,subjects:\stdClass[]}
     */
    public function get_cabinet_data(int $userid): array {
        global $DB;
        $profile = $DB->get_record('local_kaznu_univer_user', ['userid' => $userid]) ?: null;
        $subjects = $this->subjects()->get_for_user($userid);
        return ['profile' => $profile, 'subjects' => $subjects];
    }
}
