<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_kaznu', get_string('pluginname', 'local_kaznu'));

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

    $ADMIN->add('localplugins', $settings);
}
