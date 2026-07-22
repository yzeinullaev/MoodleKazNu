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
        // Also allow bare relative forms.
        if ($script !== '/course/view.php' && $script !== 'course/view.php') {
            return;
        }
    }

    // Session may not be fully bootstrapped; treat missing login as guest.
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

    $courseid = 0;
    if (isset($_GET['id'])) {
        $courseid = (int) $_GET['id'];
    }
    if ($courseid <= 1) {
        return;
    }

    // Prefer moodle_url when available.
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
 * Whether Farabi IBS styles should load on the current page.
 */
function local_kaznu_should_load_styles(): bool {
    global $PAGE, $COURSE;

    if (!empty($COURSE->id) && (int) $COURSE->id > 1) {
        return true;
    }
    if (strpos((string) $PAGE->pagetype, 'mod-') === 0) {
        return true;
    }
    if (in_array($PAGE->pagetype, ['local-kaznu-pay', 'local-kaznu-landing', 'local-kaznu-course', 'course-view-topics', 'course-view-tiles'], true)) {
        return true;
    }
    $path = $PAGE->url ? $PAGE->url->get_path() : '';
    return strpos($path, '/local/kaznu/') !== false || strpos($path, '/course/') !== false || strpos($path, '/mod/') !== false;
}

/**
 * Register plugin stylesheet.
 */
function local_kaznu_load_styles(): void {
    global $PAGE;

    if (!local_kaznu_should_load_styles()) {
        return;
    }

    $sheet = new moodle_url('/local/kaznu/styles.css', ['rev' => get_config('local_kaznu', 'version') ?: '2026072204']);
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

    $rev = get_config('local_kaznu', 'version') ?: '2026072204';
    $href = $CFG->wwwroot . '/local/kaznu/styles.css?rev=' . $rev;
    echo '<link rel="stylesheet" type="text/css" href="' . s($href) . '" />' . "\n";
}

/**
 * Arena banner + XP HUD + celebration.
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

    if (empty($COURSE->id) || (int) $COURSE->id <= 1) {
        return;
    }

    $pagetype = (string) $PAGE->pagetype;
    $oncoursehome = (strpos($pagetype, 'course-view') === 0);
    $onmod = (strpos($pagetype, 'mod-') === 0);

    if ($oncoursehome) {
        echo local_kaznu_render_course_arena($COURSE, (int) $USER->id);
        echo '<script>(function(){var a=document.querySelector("[data-kaznu-arena]");var m=document.getElementById("region-main");if(a&&m){m.insertBefore(a,m.firstChild);}})();</script>';
    }

    $xp = local_kaznu_get_xp((int) $USER->id);
    $prog = local_kaznu_xp_progress($xp);
    $hub = new moodle_url('/local/kaznu/course.php', ['id' => $COURSE->id]);
    $catalog = new moodle_url('/local/kaznu/landing.php');

    echo '<aside class="local-kaznu-hud" aria-label="XP">'
        . '<strong>' . s($prog['title']) . '</strong>'
        . '<div class="local-kaznu-hud-meta"><span>Lv ' . (int) $xp->level . '</span><span>' . (int) $xp->xp . ' XP</span></div>'
        . '<div class="local-kaznu-hud-bar"><span style="width:' . (int) $prog['pct'] . '%"></span></div>'
        . '<a href="' . $hub->out(false) . '">' . get_string('hub_syllabus', 'local_kaznu') . '</a>'
        . ' · <a href="' . $catalog->out(false) . '">' . get_string('landing_nav_courses', 'local_kaznu') . '</a>'
        . '</aside>';

    if ($onmod) {
        // Compact top strip on lesson pages.
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
