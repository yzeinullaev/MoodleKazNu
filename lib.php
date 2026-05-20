<?php
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/locallib.php');

/**
 * Load demo styles on course and payment pages.
 */
function local_kaznu_before_standard_html_head() {
    global $PAGE, $COURSE;

    $load = false;
    if (!empty($COURSE->shortname) && $COURSE->shortname === LOCAL_KAZNU_COURSE_SHORTNAME) {
        $load = true;
    }
    if ($PAGE->pagetype === 'local-kaznu-pay') {
        $load = true;
    }
    if (strpos($PAGE->url->get_path(), '/local/kaznu/pay.php') !== false) {
        $load = true;
    }

    if ($load) {
        $PAGE->requires->css('/local/kaznu/styles.css');
    }
}

/**
 * Payment link in primary navigation (demo).
 */
function local_kaznu_extend_navigation(global_navigation $nav) {
    $node = $nav->add(
        get_string('navpay', 'local_kaznu'),
        new moodle_url('/local/kaznu/pay.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_kaznu_pay',
        new pix_icon('i/course', '')
    );
    $node->showinflatnavigation = true;
}
