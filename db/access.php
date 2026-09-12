<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/kaznu:viewpay' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'guest' => CAP_ALLOW,
            'user' => CAP_ALLOW,
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
    'local/kaznu:enrolself' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_ALLOW,
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/user:viewdetails',
    ],
    /**
     * Marks portal students (legacy). Prefer viewportal + viewuniverdata.
     */
    'local/kaznu:portalstudent' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [],
    ],
    /**
     * Ordinary Farabi portal user — unified cabinet, common tabs.
     */
    'local/kaznu:viewportal' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [],
    ],
    /**
     * Univer-linked student modules (schedule/grades/attendance).
     */
    'local/kaznu:viewuniverdata' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [],
    ],
    'local/kaznu:viewcabinet' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_ALLOW,
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
    /** Upload / manage own thesis submissions (when thesis_enabled). */
    'local/kaznu:thesisupload' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_ALLOW,
            'student' => CAP_ALLOW,
        ],
    ],
    /** Supervise / review / sign assigned submissions. */
    'local/kaznu:thesissupervise' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
    /** Manage rule sets and all submissions. */
    'local/kaznu:thesismanage' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
