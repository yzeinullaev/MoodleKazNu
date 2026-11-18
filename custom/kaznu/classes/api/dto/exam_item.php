<?php
namespace local_kaznu\api\dto;

defined('MOODLE_INTERNAL') || die();

/**
 * Exam row from GET /student/examschedule/{y}/{s}/{culture}.
 *
 * Confirmed: subject, type, teacher, audienceFaculty, audience, date, time.
 */
class exam_item {
    public function __construct(
        public readonly string $subject,
        public readonly string $examtype,
        public readonly string $teacher,
        public readonly string $audiencefaculty,
        public readonly string $audience,
        public readonly string $date,
        public readonly string $time
    ) {
    }

    public function external_key(): string {
        return sha1(implode('|', ['exam', $this->subject, $this->examtype, $this->date, $this->time]));
    }
}
