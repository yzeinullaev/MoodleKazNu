<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Theme settings.
 *
 * @package   theme_farabi
 * @copyright 2026 Farabi School
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new theme_boost_admin_settingspage_tabs(
        'themesettingfarabi',
        get_string('configtitle', 'theme_farabi')
    );

    $page = new admin_settingpage('theme_farabi_general', get_string('generalsettings', 'theme_farabi'));

    $name = 'theme_farabi/preset';
    $title = get_string('preset', 'theme_farabi');
    $description = get_string('preset_desc', 'theme_farabi');
    $default = 'default.scss';
    $choices = [
        'default.scss' => 'default.scss',
        'plain.scss' => 'plain.scss',
    ];
    $setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_farabi/brandcolor';
    $title = get_string('brandcolor', 'theme_farabi');
    $description = get_string('brandcolor_desc', 'theme_farabi');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, '#F06543');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_farabi/scsspre';
    $title = get_string('rawscsspre', 'theme_farabi');
    $description = get_string('rawscsspre_desc', 'theme_farabi');
    $setting = new admin_setting_scsscode($name, $title, $description, '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_farabi/scss';
    $title = get_string('rawscss', 'theme_farabi');
    $description = get_string('rawscss_desc', 'theme_farabi');
    $setting = new admin_setting_scsscode($name, $title, $description, '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);
}
