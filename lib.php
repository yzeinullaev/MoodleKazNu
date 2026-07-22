<?php
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/locallib.php');

/**
 * Early redirect: guests on course/view → branded hub (before require_login).
 */
function local_kaznu_after_config() {
    if ((defined('CLI_SCRIPT') && CLI_SCRIPT) || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)) {
        return;
    }
    if (function_exists('during_initial_install') && during_initial_install()) {
        return;
    }

    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script === '' || substr($script, -strlen('/course/view.php')) !== '/course/view.php') {
        if ($script !== '/course/view.php' && $script !== 'course/view.php') {
            return;
        }
    }

    $loggedin = false;
    if (!empty($GLOBALS['USER']) && !empty($GLOBALS['USER']->id) && (int) $GLOBALS['USER']->id > 0) {
        $loggedin = empty($GLOBALS['USER']->username) || $GLOBALS['USER']->username !== 'guest';
        if (function_exists('isloggedin') && function_exists('isguestuser')) {
            $loggedin = isloggedin() && !isguestuser();
        }
    }

    if ($loggedin) {
        return;
    }

    $courseid = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($courseid <= 1) {
        return;
    }

    if (class_exists('moodle_url')) {
        redirect(new moodle_url('/local/kaznu/course.php', ['id' => $courseid]));
    }

    $www = !empty($GLOBALS['CFG']->wwwroot) ? $GLOBALS['CFG']->wwwroot : '';
    header('Location: ' . $www . '/local/kaznu/course.php?id=' . $courseid, true, 303);
    exit;
}

/**
 * Redirect frontpage → landing.
 */
function local_kaznu_before_http_headers() {
    if ((defined('CLI_SCRIPT') && CLI_SCRIPT) || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)) {
        return;
    }
    if (function_exists('during_initial_install') && during_initial_install()) {
        return;
    }

    global $SCRIPT;

    $script = (string) ($SCRIPT ?? '');
    if ($script === '') {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    }

    if ($script === '/index.php' || $script === 'index.php') {
        redirect(new moodle_url('/local/kaznu/landing.php'));
    }
}

/**
 * Student-facing Moodle areas that get Farabi IBS skin.
 */
function local_kaznu_is_student_shell_page(): bool {
    global $PAGE, $COURSE;

    if (!empty($COURSE->id) && (int) $COURSE->id > 1) {
        return true;
    }

    $type = (string) ($PAGE->pagetype ?? '');
    $path = $PAGE->url ? (string) $PAGE->url->get_path() : '';

    $prefixes = ['mod-', 'my-', 'user-', 'calendar-', 'grade-', 'message-', 'blog-', 'notes-', 'badges-', 'report-'];
    foreach ($prefixes as $p) {
        if (strpos($type, $p) === 0) {
            return true;
        }
    }

    if (in_array($type, [
        'local-kaznu-pay',
        'local-kaznu-landing',
        'local-kaznu-course',
        'course-view-topics',
        'course-view-tiles',
        'mycourses-index',
        'my-index',
    ], true)) {
        return true;
    }

    foreach (['/local/kaznu/', '/course/', '/mod/', '/my/', '/user/', '/calendar/', '/grade/', '/message/'] as $needle) {
        if (strpos($path, $needle) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Whether Farabi IBS styles should load.
 */
function local_kaznu_should_load_styles(): bool {
    return local_kaznu_is_student_shell_page();
}

/**
 * Register plugin stylesheet.
 */
function local_kaznu_load_styles(): void {
    global $PAGE;

    if (!local_kaznu_should_load_styles()) {
        return;
    }

    $sheet = new moodle_url('/local/kaznu/styles.css', ['rev' => get_config('local_kaznu', 'version') ?: '2026072206']);
    $PAGE->requires->css($sheet);
}

function local_kaznu_before_standard_html_head() {
    local_kaznu_load_styles();
}

/**
 * Fallback: direct link tag + brand fonts.
 */
function local_kaznu_before_standard_head_html() {
    global $CFG;

    if (!local_kaznu_should_load_styles()) {
        return;
    }

    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">' . "\n";

    $rev = get_config('local_kaznu', 'version') ?: '2026072206';
    $href = $CFG->wwwroot . '/local/kaznu/styles.css?rev=' . $rev;
    echo '<link rel="stylesheet" type="text/css" href="' . s($href) . '" />' . "\n";
}

/**
 * Arena / dashboard / XP HUD / celebration.
 */
function local_kaznu_before_footer() {
    global $USER, $COURSE, $SESSION, $PAGE;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    require_once(__DIR__ . '/locallib.php');

    $dbman = $GLOBALS['DB']->get_manager();
    if (!$dbman->table_exists('local_kaznu_xp')) {
        return;
    }

    if (!empty($SESSION->local_kaznu_celebrate)) {
        $c = $SESSION->local_kaznu_celebrate;
        unset($SESSION->local_kaznu_celebrate);
        $title = $c['levelup']
            ? get_string('xp_levelup', 'local_kaznu', (object) ['level' => $c['level'], 'title' => $c['title']])
            : get_string('xp_gained', 'local_kaznu', $c['gained']);
        echo '<div class="local-kaznu-celebrate" role="status">'
            . '<div class="lvl">' . s($title) . '</div>'
            . '<div class="xp">+' . (int) $c['gained'] . ' XP · Lv ' . (int) $c['level'] . '</div>'
            . '</div>';
    }

    $pagetype = (string) $PAGE->pagetype;
    $ondash = ($pagetype === 'my-index' || $pagetype === 'mycourses-index' || strpos($pagetype, 'my-') === 0);
    $oncoursehome = (strpos($pagetype, 'course-view') === 0);
    $onmod = (strpos($pagetype, 'mod-') === 0);

    if ($ondash) {
        echo local_kaznu_render_dashboard_arena($USER);
        echo '<script>(function(){var d=document.querySelector("[data-kaznu-dash]");var m=document.getElementById("region-main");if(d&&m){m.insertBefore(d,m.firstChild);}var h=document.querySelector("#page-header");if(h){h.classList.add("kzn-hide-default-header");}})();</script>';
    }

    if ($oncoursehome && !empty($COURSE->id) && (int) $COURSE->id > 1) {
        echo local_kaznu_render_course_arena($COURSE, (int) $USER->id);
        echo '<script>(function(){var a=document.querySelector("[data-kaznu-arena]");var m=document.getElementById("region-main");if(a&&m){m.insertBefore(a,m.firstChild);}})();</script>';
    }

    // XP HUD on student shell pages.
    if (!local_kaznu_is_student_shell_page()) {
        return;
    }

    $xp = local_kaznu_get_xp((int) $USER->id);
    $prog = local_kaznu_xp_progress($xp);
    $catalog = new moodle_url('/local/kaznu/landing.php');
    $hub = (!empty($COURSE->id) && (int) $COURSE->id > 1)
        ? new moodle_url('/local/kaznu/course.php', ['id' => $COURSE->id])
        : new moodle_url('/my/');
    $hublabel = (!empty($COURSE->id) && (int) $COURSE->id > 1)
        ? get_string('hub_syllabus', 'local_kaznu')
        : get_string('dash_title', 'local_kaznu');

    echo '<aside class="local-kaznu-hud" aria-label="XP">'
        . '<strong>' . s($prog['title']) . '</strong>'
        . '<div class="local-kaznu-hud-meta"><span>Lv ' . (int) $xp->level . '</span><span>' . (int) $xp->xp . ' XP</span></div>'
        . '<div class="local-kaznu-hud-bar"><span style="width:' . (int) $prog['pct'] . '%"></span></div>'
        . '<a href="' . $hub->out(false) . '">' . $hublabel . '</a>'
        . ' · <a href="' . $catalog->out(false) . '">' . get_string('landing_nav_courses', 'local_kaznu') . '</a>'
        . '</aside>';

    if ($onmod && !empty($COURSE->id) && (int) $COURSE->id > 1) {
        echo '<div class="local-kaznu-lesson-strip" data-kaznu-strip="1">'
            . '<a href="' . (new moodle_url('/course/view.php', ['id' => $COURSE->id]))->out(false) . '">'
            . format_string($COURSE->fullname) . '</a>'
            . '<span>Lv ' . (int) $xp->level . ' · ' . (int) $xp->xp . ' XP</span>'
            . '</div>';
        echo '<script>(function(){var s=document.querySelector("[data-kaznu-strip]");var m=document.getElementById("region-main");if(s&&m){m.insertBefore(s,m.firstChild);}})();</script>';
    }
}

/**
 * Landing + payment links in primary navigation.
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
