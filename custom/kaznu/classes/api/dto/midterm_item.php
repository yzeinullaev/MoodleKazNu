<?php
namespace local_kaznu\api\dto;

defined('MOODLE_INTERNAL') || die();

/**
 * Midterm row from GET /student/midtermschedule/{y}/{s}/{culture}.
 * Shape observed empty in live sample; fields mirror examschedule when present.
 */
class midterm_item {
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
        return sha1(implode('|', ['midterm', $this->subject, $this->examtype, $this->date, $this->time]));
    }
}
