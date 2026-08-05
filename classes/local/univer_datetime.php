<?php
namespace local_kaznu\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Date helpers for Univer cache (Asia/Almaty academic data, Moodle display).
 */
final class univer_datetime {
    public const UNIVER_TZ = 'Asia/Almaty';

    /**
     * Parse d.m.Y or d.m.y → unix timestamp at local midnight in Univer TZ, or 0.
     */
    public static function parse_date(string $raw): int {
        $raw = trim($raw);
        if ($raw === '') {
            return 0;
        }
        $tz = new \DateTimeZone(self::UNIVER_TZ);
        foreach (['d.m.Y', 'd.m.y', 'Y-m-d'] as $fmt) {
            $dt = \DateTimeImmutable::createFromFormat('!' . $fmt, $raw, $tz);
            if ($dt instanceof \DateTimeImmutable) {
                $err = \DateTimeImmutable::getLastErrors();
                if (!empty($err['warning_count']) || !empty($err['error_count'])) {
                    continue;
                }
                return $dt->getTimestamp();
            }
        }
        return 0;
    }

    /**
     * Format stored unix day for UI using user/site timezone.
     */
    public static function format_userdate(int $timestamp, int $userid = 0): string {
        if ($timestamp <= 0) {
            return '—';
        }
        return userdate($timestamp, get_string('strftimedaydate', 'langconfig'), 99, false, true);
    }
}
