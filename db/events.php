<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback' => '\local_kaznu\observer::quiz_attempt_submitted',
        'internal' => false,
        'priority' => 999,
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_graded',
        'callback' => '\local_kaznu\observer::quiz_attempt_graded',
        'internal' => false,
        'priority' => 999,
    ],
];
