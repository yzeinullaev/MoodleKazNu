<?php
namespace local_kaznu;

defined('MOODLE_INTERNAL') || die();

/**
 * Cache module names for Phase 5.
 */
final class cache_module {
    public const SCHEDULE = 'schedule';
    public const EXAM = 'exam';
    public const MIDTERM = 'midterm';
    public const ASSESSMENT = 'assessment';
    public const ATTENDANCE = 'attendance';

    public const SCHEDULE_TYPE_LESSON = 'lesson';
    public const SCHEDULE_TYPE_EXAM = 'exam';
    public const SCHEDULE_TYPE_MIDTERM = 'midterm';

    /** @return string[] */
    public static function all(): array {
        return [self::SCHEDULE, self::EXAM, self::MIDTERM, self::ASSESSMENT, self::ATTENDANCE];
    }
}
