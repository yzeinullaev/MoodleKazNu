<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Inject demo styles on the summer school course only.
 */
function local_kaznu_before_standard_html_head() {
    global $PAGE, $COURSE;

    if (empty($COURSE->shortname) || $COURSE->shortname !== 'SUMMER2026') {
        return;
    }

    $PAGE->requires->css('/local/kaznu/styles.css');
}
