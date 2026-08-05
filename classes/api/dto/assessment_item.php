<?php
namespace local_kaznu\api\dto;

defined('MOODLE_INTERNAL') || die();

/**
 * Attestation / grades row from GET /student/attestation/{y}/{s}/{culture}.
 *
 * Confirmed: subjectName, credit, resultRK1, resultRK2, result, totalRes, GPA, symbol, text.
 * Values may be numeric strings ("97") or localized ("3,33").
 */
class assessment_item {
    public function __construct(
        public readonly string $subjectname,
        public readonly string $credit,
        public readonly string $resultrk1,
        public readonly string $resultrk2,
        public readonly string $result,
        public readonly string $totalres,
        public readonly string $gpa,
        public readonly string $symbol,
        public readonly string $lettertext
    ) {
    }

    public function external_key(): string {
        return sha1(implode('|', ['assess', $this->subjectname, $this->credit]));
    }

    /** Zero is a valid grade; empty is missing. */
    public function has_result(): bool {
        return $this->result !== '' || $this->totalres !== '';
    }
}
