<?php
namespace local_kaznu\task;

use local_kaznu\service\enrolment_service;
use local_kaznu\repository\univer_subject_repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Reconcile subject cache flags with Moodle enrolments (no Univer HTTP).
 */
class reconcile_subjects extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('task_reconcile_subjects', 'local_kaznu');
    }

    public function execute(): void {
        global $CFG;
        require_once($CFG->libdir . '/enrollib.php');

        $repo = new univer_subject_repository();
        $enrol = new enrolment_service($repo);
        $rows = $repo->get_all(null, 5000);
        $ensured = 0;
        $reconciled = 0;
        foreach ($rows as $row) {
            try {
                // Cache-only + Moodle enrol API. Never calls Univer.
                if ((string) $row->status === \local_kaznu\univer_status::ACTIVE) {
                    $enrol->ensure_for_subject_row($row);
                    $ensured++;
                } else {
                    $enrol->reconcile_row($row);
                    $reconciled++;
                }
            } catch (\Throwable $e) {
                mtrace('reconcile_subjects row ' . $row->id . ' failed: ' . get_class($e));
            }
        }
        mtrace('reconcile_subjects ensured=' . $ensured . ' reconciled=' . $reconciled);
    }
}
