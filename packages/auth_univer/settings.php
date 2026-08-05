<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'auth_univer/info',
        get_string('settings_heading', 'auth_univer'),
        get_string('settings_desc', 'auth_univer')
    ));
}
