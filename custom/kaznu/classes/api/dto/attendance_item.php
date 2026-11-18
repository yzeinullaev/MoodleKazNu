<?php
namespace local_kaznu\api\dto;

defined('MOODLE_INTERNAL') || die();

/**
 * Attendance row from GET /student/attendance/{y}/{s}/{culture}.
 *
 * Live shape: data is array of triples:
 *   [ {name_1, name_2, name_3, groupInfo}, {sum}, {sum} ]
 * API does not expose present/absent counts unambiguously — store two sum rates as returned.
 */
class attendance_item {
    public function __construct(
        public readonly string $subjectname,
        public readonly string $label,
        public readonly string $period,
        public readonly string $groupinfo,
        public readonly string $rate1,
        public readonly string $rate2
    ) {
    }

    public function external_key(): string {
        $base = $this->groupinfo !== '' ? $this->groupinfo : ($this->subjectname . '|' . $this->period);
        return sha1('att|' . $base);
    }
}
