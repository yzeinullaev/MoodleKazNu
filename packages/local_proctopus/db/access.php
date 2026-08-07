<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/proctopus:configure' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
    'local/proctopus:viewstatus' => [
        'captype' => 'read', 'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'student' => CAP_ALLOW, 'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW,
        ],
    ],
    'local/proctopus:viewreports' => [
        'captype' => 'read', 'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW,
        ],
    ],
    'local/proctopus:retry' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_COURSE,
        'archetypes' => ['editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
    ],
    'local/proctopus:managecallbacks' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
];
