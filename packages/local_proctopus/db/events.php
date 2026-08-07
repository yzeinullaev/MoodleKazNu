<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\mod_quiz\event\attempt_started',
        'callback' => '\local_proctopus\observer::attempt_started',
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback' => '\local_proctopus\observer::attempt_submitted',
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_becameoverdue',
        'callback' => '\local_proctopus\observer::attempt_becameoverdue',
    ],
];
