<?php
namespace local_kaznu\api\dto;

defined('MOODLE_INTERNAL') || die();

/**
 * One discipline row from /student/groupList (cache candidate).
 */
class subject {
    public function __construct(
        public readonly int $subjectid,
        public readonly string $subjectname,
        public readonly int $groupid,
        public readonly int $planposid,
        public readonly int $academyear,
        public readonly int $academsemester,
        public readonly int $eductypeid,
        public readonly string $eductypename,
        public readonly int $teacherid,
        public readonly int $langid
    ) {
    }

    /**
     * Moodle course.idnumber contract.
     */
    public function course_idnumber(): string {
        return \local_kaznu\local\course_idnumber::format(
            $this->subjectid,
            $this->academyear,
            $this->academsemester
        );
    }
}
