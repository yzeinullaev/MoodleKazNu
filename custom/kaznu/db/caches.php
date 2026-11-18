<?php
defined('MOODLE_INTERNAL') || die();

$definitions = [
    'login_ratelimit' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 3600,
        'staticacceleration' => true,
    ],
];
