<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Login layout for Farabi School (renders theme_farabi/login mustache).
 *
 * @package   theme_farabi
 * @copyright 2026 Farabi School
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$bodyattributes = $OUTPUT->body_attributes(['theme-farabi']);

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, [
        'context' => context_course::instance(SITEID),
        'escape' => false,
    ]),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
];

echo $OUTPUT->render_from_template('theme_farabi/login', $templatecontext);
