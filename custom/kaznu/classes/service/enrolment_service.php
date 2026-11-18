<?php
namespace local_kaznu\service;

use local_kaznu\api\dto\subject;
use local_kaznu\event\student_course_not_found;
use local_kaznu\event\student_enrolled;
use local_kaznu\local\course_idnumber;
use local_kaznu\repository\sync_log_repository;
use local_kaznu\repository\univer_subject_repository;
use local_kaznu\univer_status;

defined('MOODLE_INTERNAL') || die();

/**
 * Moodle course enrolment for Univer subject cache rows (enrol API only).
 * Never unenrols in Phase 4.
 */
class enrolment_service {
    public function __construct(
        private readonly ?univer_subject_repository $subjects = null,
        private readonly ?sync_log_repository $logs = null
    ) {
    }

    private function subjects(): univer_subject_repository {
        return $this->subjects ?? new univer_subject_repository();
    }

    private function logs(): sync_log_repository {
        return $this->logs ?? new sync_log_repository();
    }

    /**
     * Ensure student is enrolled in the Moodle course matching subject idnumber.
     *
     * @return array{status:string,courseid:int,enrolled:bool}
     */
    public function ensure_for_subject_row(\stdClass $row): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/enrollib.php');

        $userid = (int) $row->userid;
        $idnumber = (string) $row->course_idnumber;
        if ($idnumber === '') {
            $idnumber = course_idnumber::format(
                (int) $row->subjectid,
                (int) $row->academyear,
                (int) $row->academsemester
            );
        }

        $course = $DB->get_record('course', ['idnumber' => $idnumber]);
        if (!$course) {
            $this->subjects()->update_enrol_state((int) $row->id, 0, 0);
            $this->logs()->add($userid, 'enrol', 'course_not_found', (int) $row->subjectid, 0, $idnumber);
            student_course_not_found::create_safe($userid, (int) $row->subjectid, $idnumber)->trigger();
            return ['status' => 'not_found', 'courseid' => 0, 'enrolled' => false];
        }

        $courseid = (int) $course->id;
        $context = \context_course::instance($courseid);
        if (is_enrolled($context, $userid, '', true)) {
            $this->subjects()->update_enrol_state((int) $row->id, $courseid, 1);
            $this->logs()->add($userid, 'enrol', 'already', (int) $row->subjectid, $courseid, $idnumber);
            return ['status' => 'already', 'courseid' => $courseid, 'enrolled' => true];
        }

        try {
            $this->enrol_manual($course, $userid);
        } catch (\Throwable $e) {
            $this->logs()->add($userid, 'enrol', 'error', (int) $row->subjectid, $courseid, 'enrol_failed');
            debugging('Univer enrol error course=' . $courseid, DEBUG_DEVELOPER);
            return ['status' => 'error', 'courseid' => $courseid, 'enrolled' => false];
        }

        $this->subjects()->update_enrol_state((int) $row->id, $courseid, 1);
        $this->logs()->add($userid, 'enrol', 'enrolled', (int) $row->subjectid, $courseid, $idnumber);
        student_enrolled::create_safe($userid, $courseid, (int) $row->subjectid)->trigger();
        return ['status' => 'enrolled', 'courseid' => $courseid, 'enrolled' => true];
    }

    /**
     * Reconcile cache flags with live Moodle enrol state (no API, no unenrol).
     */
    public function reconcile_row(\stdClass $row): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/enrollib.php');

        $userid = (int) $row->userid;
        $courseid = (int) $row->moodlecourseid;
        if ($courseid <= 0 && !empty($row->course_idnumber)) {
            $course = $DB->get_record('course', ['idnumber' => $row->course_idnumber]);
            $courseid = $course ? (int) $course->id : 0;
        }

        $isenrolled = false;
        if ($courseid > 0) {
            $context = \context_course::instance($courseid, IGNORE_MISSING);
            if ($context) {
                $isenrolled = is_enrolled($context, $userid, '', true);
            }
        }

        if ((string) $row->status === univer_status::STALE) {
            // Never unenrol; flip flag only when not actually enrolled.
            $this->subjects()->update_enrol_state((int) $row->id, $courseid, $isenrolled ? 1 : 0);
            return;
        }

        if ((string) $row->status === univer_status::ACTIVE && $courseid > 0) {
            $this->subjects()->update_enrol_state((int) $row->id, $courseid, $isenrolled ? 1 : 0);
        }
    }

    private function enrol_manual(\stdClass $course, int $userid): void {
        global $DB;

        $role = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            throw new \moodle_exception('enroldisabled', 'enrol');
        }

        $instance = null;
        foreach (enrol_get_instances($course->id, true) as $inst) {
            if ($inst->enrol === 'manual') {
                $instance = $inst;
                break;
            }
        }
        if (!$instance) {
            $id = $plugin->add_instance($course);
            $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        }

        if ($DB->record_exists('user_enrolments', ['userid' => $userid, 'enrolid' => $instance->id])) {
            return;
        }

        $plugin->enrol_user($instance, $userid, $role->id);
    }
}
