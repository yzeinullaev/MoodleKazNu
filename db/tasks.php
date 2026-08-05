<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\local_kaznu\task\reconcile_subjects',
        'blocking' => 0,
        'minute' => '15',
        'hour' => '3',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => '\local_kaznu\task\cleanup_cabinet_cache',
        'blocking' => 0,
        'minute' => '40',
        'hour' => '4',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '0',
    ],
];
