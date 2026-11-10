<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_kaznu', get_string('settings', 'local_kaznu'));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_kaznu/openai_apikey',
        get_string('openai_apikey', 'local_kaznu'),
        '',
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_kaznu/openai_model',
        get_string('openai_model', 'local_kaznu'),
        '',
        'gpt-4o-mini',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_kaznu/enable_web_search',
        get_string('enable_web_search', 'local_kaznu'),
        get_string('websearchhelp', 'local_kaznu'),
        1
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_kaznu/tavily_apikey',
        get_string('tavily_apikey', 'local_kaznu'),
        get_string('tavily_apikey_desc', 'local_kaznu'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_kaznu/max_history',
        get_string('max_history', 'local_kaznu'),
        '',
        '40',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
