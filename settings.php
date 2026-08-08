<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_kaznu', get_string('pluginname', 'local_kaznu'));

    $settings->add(new admin_setting_heading(
        'local_kaznu/payheading',
        get_string('paybadge', 'local_kaznu'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_kaznu/paymenttoken',
        get_string('paymenttoken', 'local_kaznu'),
        get_string('paymenttoken_desc', 'local_kaznu'),
        'DEMO-KZN-2026',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_kaznu/paymentprice',
        get_string('paymentprice', 'local_kaznu'),
        get_string('paymentprice_desc', 'local_kaznu'),
        '25 000 ₸',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_heading(
        'local_kaznu/univerheading',
        get_string('univer_settings', 'local_kaznu'),
        get_string('univer_settings_desc', 'local_kaznu')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_kaznu/univer_enabled',
        get_string('univer_enabled', 'local_kaznu'),
        get_string('univer_enabled_desc', 'local_kaznu'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_kaznu/univer_unified_login_enabled',
        get_string('univer_unified_login_enabled', 'local_kaznu'),
        get_string('univer_unified_login_enabled_desc', 'local_kaznu'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_kaznu/univer_fallback_auth_methods',
        get_string('univer_fallback_auth_methods', 'local_kaznu'),
        get_string('univer_fallback_auth_methods_desc', 'local_kaznu'),
        'manual,email',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_kaznu/univer_faculty_filter_enabled',
        get_string('univer_faculty_filter_enabled', 'local_kaznu'),
        get_string('univer_faculty_filter_enabled_desc', 'local_kaznu'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_kaznu/univer_allowed_faculty_ids',
        get_string('univer_allowed_faculty_ids', 'local_kaznu'),
        get_string('univer_allowed_faculty_ids_desc', 'local_kaznu'),
        '28',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_kaznu/univer_login_allowlist',
        get_string('univer_login_allowlist', 'local_kaznu'),
        get_string('univer_login_allowlist_desc', 'local_kaznu'),
        'oxikbayev.yernur',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_kaznu/univer_baseurl',
        get_string('univer_baseurl', 'local_kaznu'),
        get_string('univer_baseurl_desc', 'local_kaznu'),
        'https://univerapi.kaznu.kz',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_kaznu/univer_timeout',
        get_string('univer_timeout', 'local_kaznu'),
        get_string('univer_timeout_desc', 'local_kaznu'),
        20,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configselect(
        'local_kaznu/univer_culture',
        get_string('univer_culture', 'local_kaznu'),
        get_string('univer_culture_desc', 'local_kaznu'),
        'ru-RU',
        [
            'ru-RU' => 'ru-RU',
            'kk-KZ' => 'kk-KZ',
            'en-US' => 'en-US',
        ]
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_kaznu/univer_auto_unenrol',
        get_string('univer_auto_unenrol', 'local_kaznu'),
        get_string('univer_auto_unenrol_desc', 'local_kaznu'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_kaznu/univer_auth_update',
        get_string('univer_auth_update', 'local_kaznu'),
        get_string('univer_auth_update_desc', 'local_kaznu'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_kaznu/univer_rl_login',
        get_string('univer_rl_login', 'local_kaznu'),
        get_string('univer_rl_login_desc', 'local_kaznu'),
        5,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'local_kaznu/univer_rl_ip',
        get_string('univer_rl_ip', 'local_kaznu'),
        get_string('univer_rl_ip_desc', 'local_kaznu'),
        100,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'local_kaznu/univer_rl_window',
        get_string('univer_rl_window', 'local_kaznu'),
        get_string('univer_rl_window_desc', 'local_kaznu'),
        900,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'local_kaznu/univer_rl_lockout',
        get_string('univer_rl_lockout', 'local_kaznu'),
        get_string('univer_rl_lockout_desc', 'local_kaznu'),
        900,
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'local_kaznu/univercabinetheading',
        get_string('univer_cabinet_settings', 'local_kaznu'),
        get_string('univer_cabinet_settings_desc', 'local_kaznu')
    ));
    $settings->add(new admin_setting_configtext(
        'local_kaznu/univer_schedule_ttl',
        get_string('univer_schedule_ttl', 'local_kaznu'),
        get_string('univer_schedule_ttl_desc', 'local_kaznu'),
        900,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'local_kaznu/univer_assessment_ttl',
        get_string('univer_assessment_ttl', 'local_kaznu'),
        get_string('univer_assessment_ttl_desc', 'local_kaznu'),
        1800,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'local_kaznu/univer_attendance_ttl',
        get_string('univer_attendance_ttl', 'local_kaznu'),
        get_string('univer_attendance_ttl_desc', 'local_kaznu'),
        1800,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'local_kaznu/univer_cache_retention_days',
        get_string('univer_cache_retention_days', 'local_kaznu'),
        get_string('univer_cache_retention_days_desc', 'local_kaznu'),
        180,
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
