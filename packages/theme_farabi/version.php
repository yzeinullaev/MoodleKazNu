<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Theme version for Farabi School.
 *
 * @package   theme_farabi
 * @copyright 2026 Farabi School
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'theme_farabi';
$plugin->version   = 2026080703;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_RC;
$plugin->release   = '1.0.0-portal-auth-staging';
$plugin->dependencies = [
    'theme_boost' => 2024100700,
];
