<?php
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/locallib.php');

/**
 * Redirect Moodle site home to the branded Farabi landing.
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

    // Only the public site front page — never /login/index.php or other scripts.
    if ($script !== '/index.php' && $script !== 'index.php') {
        return;
    }

    redirect(new moodle_url('/local/kaznu/landing.php'));
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

    $sheet = new moodle_url('/local/kaznu/styles.css', ['rev' => get_config('local_kaznu', 'version') ?: '2026072201']);
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

    $rev = get_config('local_kaznu', 'version') ?: '2026072201';
    $href = $CFG->wwwroot . '/local/kaznu/styles.css?rev=' . $rev;
    echo '<link rel="stylesheet" type="text/css" href="' . s($href) . '" />' . "\n";
}

/**
 * XP HUD + celebration on course / activity pages.
 */
function local_kaznu_before_footer() {
    global $USER, $COURSE, $SESSION;

    if (!isloggedin() || isguestuser()) {
        return;
    }
    if (empty($COURSE->id) || (int) $COURSE->id <= 1) {
        if (empty($SESSION->local_kaznu_celebrate)) {
            return;
        }
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
