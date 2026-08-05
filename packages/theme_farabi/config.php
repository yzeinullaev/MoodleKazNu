<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Farabi School theme config (Boost child).
 *
 * @package   theme_farabi
 * @copyright 2026 Farabi School
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');

$THEME->name = 'farabi';
$THEME->sheets = [];
$THEME->editor_sheets = [];
$THEME->editor_scss = ['editor'];
$THEME->usefallback = true;
$THEME->scss = function($theme) {
    return theme_farabi_get_main_scss_content($theme);
};
// Layouts inherited from Boost parent, except branded login.
$THEME->layouts = [
    'login' => [
        'file' => 'login.php',
        'regions' => [],
        'options' => ['langmenu' => true],
    ],
];
$THEME->parents = ['boost'];
$THEME->enable_dock = false;
$THEME->yuicssmodules = [];
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->requiredblocks = '';
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_FLATNAV;
$THEME->iconsystem = \core\output\icon_system::FONTAWESOME;
$THEME->haseditswitch = true;
$THEME->usescourseindex = true;
$THEME->pre_scss_callback = 'theme_farabi_get_pre_scss';
$THEME->extrascsscallback = 'theme_farabi_get_extra_scss';
$THEME->prescsscallback = 'theme_farabi_get_pre_scss';
// Remove activity navigation title default noise when supported.
$THEME->activityheaderconfig = [
    'notitle' => true,
];
