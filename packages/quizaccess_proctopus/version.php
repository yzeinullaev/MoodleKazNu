<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'quizaccess_proctopus';
$plugin->version   = 2026081103;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.3.2-gate-html-fix';
$plugin->dependencies = [
    'local_proctopus' => 2026081103,
];
