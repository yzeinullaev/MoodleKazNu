<?php
namespace local_kaznu\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Moodle course.idnumber ↔ Univer subject mapping.
 */
final class course_idnumber {
    public const PREFIX = 'UNIVER';

    /**
     * Preferred format: UNIVER:{subjectId}:{year}:{semester}
     */
    public static function format(int $subjectid, int $year, int $semester): string {
        return self::PREFIX . ':' . $subjectid . ':' . $year . ':' . $semester;
    }

    /**
     * @return array{subjectid:int,year:int,semester:int}|null
     */
    public static function parse(string $idnumber): ?array {
        $idnumber = trim($idnumber);
        if (preg_match('/^UNIVER:(\d+):(\d+):(\d+)$/', $idnumber, $m)) {
            return [
                'subjectid' => (int) $m[1],
                'year' => (int) $m[2],
                'semester' => (int) $m[3],
            ];
        }
        // Legacy / simple form: bare subjectId digits only.
        if (preg_match('/^\d+$/', $idnumber)) {
            return [
                'subjectid' => (int) $idnumber,
                'year' => 0,
                'semester' => 0,
            ];
        }
        return null;
    }
}
