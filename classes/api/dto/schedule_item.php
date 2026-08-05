<?php
namespace local_kaznu\api\dto;

defined('MOODLE_INTERNAL') || die();

/**
 * Weekly lesson row from GET /student/schedule/{y}/{s}/{day}/{culture}.
 *
 * Confirmed fields (live API, 2025/1): type, subject, eductype, teacher,
 * audienceFaculty, audience, timeBegin, timeEnd, from, to, denominator.
 */
class schedule_item {
    public function __construct(
        public readonly int $weekday,
        public readonly int $type,
        public readonly string $subject,
        public readonly string $eductype,
        public readonly string $teacher,
        public readonly string $audiencefaculty,
        public readonly string $audience,
        public readonly string $timebegin,
        public readonly string $timeend,
        public readonly string $datefrom,
        public readonly string $dateto,
        public readonly int $denominator
    ) {
    }

    /** Stable technical key (no FIO secrets). */
    public function external_key(): string {
        return sha1(implode('|', [
            'lesson',
            $this->weekday,
            $this->type,
            $this->subject,
            $this->eductype,
            $this->timebegin,
            $this->timeend,
            $this->datefrom,
            $this->dateto,
            $this->denominator,
        ]));
    }
}
