<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'auth_univer';
$plugin->version   = 2026080703;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_RC;
$plugin->release   = '1.0.0-portal-auth-staging';
$plugin->dependencies = [
    'local_kaznu' => 2026080703,
];
