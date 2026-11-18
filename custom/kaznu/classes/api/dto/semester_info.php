<?php
namespace local_kaznu\api\dto;

defined('MOODLE_INTERNAL') || die();

/**
 * Current academic period from /student/semesterInfo.
 */
class semester_info {
    public function __construct(
        public readonly int $currentyear,
        public readonly int $currentsemester,
        public readonly int $semesterscount,
        public readonly string $currentrk
    ) {
    }
}
