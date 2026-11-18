<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Local Kaznu hook callbacks.
 *
 * Login CTA removed (Phase 6B). Primary nav simplified for portal users (Phase 6C UX).
 */
$callbacks = [
    [
        'hook' => \core\hook\navigation\primary_extend::class,
        'callback' => [\local_kaznu\hook\primary_nav::class, 'simplify'],
        'priority' => 500,
    ],
];
