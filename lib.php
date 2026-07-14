<?php
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/locallib.php');

/**
 * Redirect Moodle site home to the branded Farabi landing.
 */
function local_kaznu_before_http_headers() {
    global $PAGE, $SCRIPT;

    if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
        return;
    }
    if (during_initial_install()) {
        return;
    }

    // Frontpage / site index → landing.
    $isfront = (!empty($SCRIPT) && ($SCRIPT === '/index.php' || $SCRIPT === '/'))
        || (!empty($PAGE->pagetype) && $PAGE->pagetype === 'site-index');

    if (!$isfront) {
        return;
    }

    // Avoid redirect loop or interfering with login/AJAX.
    $path = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    if (strpos($path, '/local/kaznu/') !== false || strpos($path, '/login/') !== false) {
        return;
    }

    redirect(new moodle_url('/local/kaznu/landing.php'));
}

/**
 * Whether KazNU demo styles should load on the current page.
 */
function local_kaznu_should_load_styles(): bool {
    global $PAGE, $COURSE;

    if (!empty($COURSE->shortname) && $COURSE->shortname === LOCAL_KAZNU_COURSE_SHORTNAME) {
        return true;
    }
    if (in_array($PAGE->pagetype, ['local-kaznu-pay', 'local-kaznu-landing'], true)) {
        return true;
    }
    $path = $PAGE->url->get_path();
    return strpos($path, '/local/kaznu/') !== false;
}

/**
 * Register plugin stylesheet (Moodle queue + direct link for reliability).
 */
function local_kaznu_load_styles(): void {
    global $PAGE, $CFG;

    if (!local_kaznu_should_load_styles()) {
        return;
    }

    $sheet = new moodle_url('/local/kaznu/styles.css', ['rev' => get_config('local_kaznu', 'version') ?: '1']);
    $PAGE->requires->css($sheet);
}

function local_kaznu_before_standard_html_head() {
    local_kaznu_load_styles();
}

/**
 * Fallback: direct link tag when Moodle does not emit queued local CSS.
 */
function local_kaznu_before_standard_head_html() {
    global $CFG;

    if (!local_kaznu_should_load_styles()) {
        return;
    }

    $rev = get_config('local_kaznu', 'version') ?: '1';
    $href = $CFG->wwwroot . '/local/kaznu/styles.css?rev=' . $rev;
    echo '<link rel="stylesheet" type="text/css" href="' . s($href) . '" />' . "\n";
}

/**
 * Landing + payment links in primary navigation (demo).
 */
function local_kaznu_extend_navigation(global_navigation $nav) {
    $home = $nav->add(
        get_string('landing_title', 'local_kaznu'),
        new moodle_url('/local/kaznu/landing.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_kaznu_landing',
        new pix_icon('i/home', '')
    );
    $home->showinflatnavigation = true;

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
