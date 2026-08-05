<?php
namespace local_kaznu;

defined('MOODLE_INTERNAL') || die();

/**
 * Status values for cached Univer rows.
 */
final class univer_status {
    public const ACTIVE = 'active';
    public const STALE = 'stale';
    public const ERROR = 'error';
    public const UNMAPPED = 'unmapped';
}
