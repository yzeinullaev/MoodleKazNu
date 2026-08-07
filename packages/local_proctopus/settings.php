<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_proctopus', get_string('pluginname', 'local_proctopus'));

    $settings->add(new admin_setting_heading('local_proctopus/hflags', get_string('settings_flags', 'local_proctopus'),
        get_string('settings_flags_desc', 'local_proctopus')));

    $settings->add(new admin_setting_configcheckbox(
        'local_proctopus/proctopus_enabled',
        get_string('proctopus_enabled', 'local_proctopus'),
        get_string('proctopus_enabled_desc', 'local_proctopus'),
        0
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_proctopus/proctopus_legacy_mode',
        get_string('proctopus_legacy_mode', 'local_proctopus'),
        get_string('proctopus_legacy_mode_desc', 'local_proctopus'),
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_proctopus/proctopus_live_calls_enabled',
        get_string('proctopus_live_calls_enabled', 'local_proctopus'),
        get_string('proctopus_live_calls_enabled_desc', 'local_proctopus'),
        0
    ));

    $settings->add(new admin_setting_heading('local_proctopus/hendpoints', get_string('settings_endpoints', 'local_proctopus'), ''));

    $settings->add(new admin_setting_configtext(
        'local_proctopus/proctopus_baseurl',
        get_string('proctopus_baseurl', 'local_proctopus'),
        get_string('proctopus_baseurl_desc', 'local_proctopus'),
        'https://farabi-proctoring.kaznu.kz',
        PARAM_URL
    ));
    $settings->add(new admin_setting_configtext(
        'local_proctopus/proctopus_account_name',
        get_string('proctopus_account_name', 'local_proctopus'),
        get_string('proctopus_account_name_desc', 'local_proctopus'),
        'farabi_business',
        PARAM_ALPHANUMEXT
    ));
    $settings->add(new admin_setting_configtext(
        'local_proctopus/proctopus_start_path',
        get_string('proctopus_start_path', 'local_proctopus'),
        get_string('proctopus_start_path_desc', 'local_proctopus'),
        '/integration/simple/farabi_business/start/',
        PARAM_RAW_TRIMMED
    ));
    $settings->add(new admin_setting_configtext(
        'local_proctopus/proctopus_callback_url',
        get_string('proctopus_callback_url', 'local_proctopus'),
        get_string('proctopus_callback_url_desc', 'local_proctopus'),
        'https://farabischool.kz/proctoring/proctopus/result',
        PARAM_URL
    ));

    $settings->add(new admin_setting_heading('local_proctopus/hjwt', get_string('settings_jwt', 'local_proctopus'),
        get_string('settings_jwt_desc', 'local_proctopus')));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_proctopus/proctopus_jwt_secret',
        get_string('proctopus_jwt_secret', 'local_proctopus'),
        get_string('proctopus_jwt_secret_desc', 'local_proctopus'),
        ''
    ));
    $settings->add(new admin_setting_configtext(
        'local_proctopus/proctopus_jwt_algorithm',
        get_string('proctopus_jwt_algorithm', 'local_proctopus'),
        get_string('proctopus_jwt_algorithm_desc', 'local_proctopus'),
        'HS256',
        PARAM_ALPHANUMEXT
    ));
    $settings->add(new admin_setting_configtext(
        'local_proctopus/proctopus_token_ttl',
        get_string('proctopus_token_ttl', 'local_proctopus'),
        '',
        300,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'local_proctopus/proctopus_clock_skew',
        get_string('proctopus_clock_skew', 'local_proctopus'),
        '',
        60,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'local_proctopus/proctopus_issuer',
        get_string('proctopus_issuer', 'local_proctopus'),
        '',
        'farabischool.kz',
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configtext(
        'local_proctopus/proctopus_default_duration',
        get_string('proctopus_default_duration', 'local_proctopus'),
        get_string('proctopus_default_duration_desc', 'local_proctopus'),
        60,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_proctopus/proctopus_connect_timeout', get_string('proctopus_connect_timeout', 'local_proctopus'), '', 5, PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'local_proctopus/proctopus_timeout', get_string('proctopus_timeout', 'local_proctopus'), '', 20, PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'local_proctopus/proctopus_default_language', get_string('proctopus_default_language', 'local_proctopus'), '', 'ru', PARAM_ALPHA
    ));
    $settings->add(new admin_setting_configtext(
        'local_proctopus/proctopus_default_mode', get_string('proctopus_default_mode', 'local_proctopus'), '', 'online', PARAM_TEXT
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_proctopus/proctopus_debug', get_string('proctopus_debug', 'local_proctopus'), get_string('proctopus_debug_desc', 'local_proctopus'), 0
    ));
    $settings->add(new admin_setting_configtext(
        'local_proctopus/proctopus_retention_days',
        get_string('proctopus_retention_days', 'local_proctopus'),
        get_string('proctopus_retention_days_desc', 'local_proctopus'),
        180,
        PARAM_INT
    ));

    // Optional future API credentials — unused in legacy flow.
    $settings->add(new admin_setting_heading('local_proctopus/hapiopt', get_string('settings_api_optional', 'local_proctopus'),
        get_string('settings_api_optional_desc', 'local_proctopus')));
    $settings->add(new admin_setting_configtext(
        'local_proctopus/proctopus_api_login', get_string('proctopus_api_login', 'local_proctopus'),
        get_string('proctopus_api_login_desc', 'local_proctopus'), '', PARAM_TEXT
    ));
    $settings->add(new admin_setting_configpasswordunmask(
        'local_proctopus/proctopus_api_password', get_string('proctopus_api_password', 'local_proctopus'),
        get_string('proctopus_api_password_desc', 'local_proctopus'), ''
    ));

    $ADMIN->add('localplugins', $settings);
}
